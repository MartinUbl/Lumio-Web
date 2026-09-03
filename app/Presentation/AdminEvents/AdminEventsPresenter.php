<?php
declare(strict_types=1);

namespace App\Presentation\AdminEvents;

use App\Model\Entities\Event;
use App\Model\Entities\Tag;
use App\Model\Repositories\IAttendanceRepository;
use App\Model\Repositories\IEventRepository;
use App\Model\Repositories\IExpertRepository;
use App\Model\Repositories\ITagRepository;
use App\Model\Services\EventApplicationService;
use App\Presentation\Accessory\DiscordIntegration;
use App\Presentation\BaseAdminPresenter;
use DateTimeImmutable;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Random;

final class AdminEventsPresenter extends BaseAdminPresenter
{
    /** @var array<string, string> */
    private const array AUDIENCE_ROLE_OPTIONS = [
        'student' => 'Student ZČU',
        'absolvent' => 'Absolvent ZČU',
        'zamestnanec' => 'Zaměstnanec ZČU',
        'stredoskolak' => 'Žák SŠ',
        'jine' => 'Ostatní',
    ];

    /** @var array<string, string> */
    private const array ATTACHMENT_MIME_MAP = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/x-png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** @var array<string, string> */
    private const array ATTACHMENT_EXTENSION_MAP = [
        'pdf' => 'pdf',
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
        'gif' => 'gif',
    ];

    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly ITagRepository $tagRepository,
        private readonly IExpertRepository $expertRepository,
        private readonly IAttendanceRepository $attendanceRepository,
        private readonly EventApplicationService $eventApplicationService,
        private readonly DiscordIntegration $discord,
        private readonly string $uploadDir,
    ) {
        parent::__construct();
    }

    public function renderDefault(?int $attendeeEventId = null): void
    {
        $events = $this->eventRepository->findAll();
        $attendeeCounts = [];
        foreach ($events as $event) {
            if ($event->id !== null) {
                $attendeeCounts[$event->id] = $this->attendanceRepository->countByEvent($event->id);
            }
        }

        $selectedAttendeeEvent = $attendeeEventId !== null ? $this->eventRepository->findById($attendeeEventId) : null;

        $this->template->events = $events;
        $this->template->attendeeCounts = $attendeeCounts;
        $this->template->selectedAttendeeEvent = $selectedAttendeeEvent;
        $this->template->selectedAttendeeNames = $selectedAttendeeEvent?->id !== null
            ? $this->attendanceRepository->findAttendeeNamesByEvent($selectedAttendeeEvent->id)
            : [];
    }

    #[NoReturn]
    public function handleApprove(int $id): void
    {
        $event = $this->eventApplicationService->setStatus($id, Event::STATUS_APPROVED);
        if ($event !== null) {
            $this->flashMessage('Akce byla schválena.', 'success');
            $this->discord->postEventNotification($event);
        }

        $this->redirect('this');
    }

    #[NoReturn]
    public function handleReject(int $id): void
    {
        $event = $this->eventApplicationService->setStatus($id, Event::STATUS_REJECTED);
        if ($event !== null) {
            $this->flashMessage('Akce byla zamítnuta.', 'info');
        }

        $this->redirect('this');
    }


    #[NoReturn]
    public function handleHide(int $id): void
    {
        $event = $this->eventApplicationService->setStatus($id, Event::STATUS_REJECTED);
        if ($event !== null) {
            $this->flashMessage('Akce byla skryta.', 'success');
        }

        $this->redirect('this');
    }

    protected function createComponentEventForm(): Form
    {
        $form = new Form();
        $form->addProtection('Platnost formuláře vypršela, zkuste to prosím znovu.');
        $form->addHidden('id');

        $form->addText('name', 'Název akce')
            ->setRequired('Vyplňte název akce.');

        $form->addTextArea('description', 'Popis')
            ->setRequired('Vyplňte popis akce.');

        $form->addText('organiser', 'Organizátor')
            ->setRequired('Vyplňte organizátora.');

        $form->addMultiSelect('audienceRoles', 'Pro koho je akce určena', self::AUDIENCE_ROLE_OPTIONS)
            ->setRequired('Vyberte alespoň jednu cílovou skupinu.')
            ->setHtmlAttribute('size', 5);

        $form->addSelect('categoryTag', 'Kategorie', $this->getTagOptionsByType(Tag::TYPE_CATEGORY))
            ->setPrompt('Vyberte kategorii')
            ->setRequired('Vyberte kategorii.');

        $form->addMultiSelect('areaTags', 'Obory', $this->getTagOptionsByType(Tag::TYPE_AREA))
            ->setHtmlAttribute('size', 6);

        $expertOptions = [0 => 'Bez odborníka'];
        foreach ($this->expertRepository->findAll() as $expert) {
            if ($expert->id === null) {
                continue;
            }

            $expertOptions[$expert->id] = $expert->name . ($expert->active ? '' : ' (skrytý)');
        }

        $form->addSelect('expertId', 'Připnutý odborník', $expertOptions)
            ->setDefaultValue(0);

        $form->addText('date', 'Datum a čas')
            ->setRequired('Vyplňte datum a čas akce.')
            ->setHtmlAttribute('placeholder', '2026-06-01 17:00');

        $form->addUpload('attachment', 'Soubor k akci')
            ->setRequired(false);

        $form->addCheckbox('removeAttachment', 'Odebrat stávající soubor');

        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'eventFormSucceeded'];

        return $form;
    }

    #[NoReturn]
    public function eventFormSucceeded(Form $form, array $values): void
    {
        $eventId = isset($values['id']) && $values['id'] !== '' ? (int) $values['id'] : null;
        $existingEvent = $eventId !== null ? $this->eventRepository->findById($eventId) : null;

        $audienceRoles = array_values(array_unique(array_map('strval', $values['audienceRoles'] ?? [])));
        if (array_diff($audienceRoles, array_keys(self::AUDIENCE_ROLE_OPTIONS)) !== []) {
            $form->addError('Vybrané cílové skupiny nejsou platné. Obnov prosím formulář a zkus to znovu.');
            return;
        }

        $allowedAreaTags = array_keys($this->getTagOptionsByType(Tag::TYPE_AREA));
        $areaTags = array_values(array_unique(array_map('strval', $values['areaTags'] ?? [])));
        if (array_diff($areaTags, $allowedAreaTags) !== []) {
            $form->addError('Vybrané obory nejsou platné. Obnov prosím formulář a zkus to znovu.');
            return;
        }

        $allowedCategoryTags = array_keys($this->getTagOptionsByType(Tag::TYPE_CATEGORY));
        $categoryTag = isset($values['categoryTag']) && $values['categoryTag'] !== '' ? (string) $values['categoryTag'] : null;
        if ($categoryTag === null || !in_array($categoryTag, $allowedCategoryTags, true)) {
            $form->addError('Vyberte platnou kategorii.');
            return;
        }

        $expertId = (int) ($values['expertId'] ?? 0);
        if ($expertId > 0 && $this->expertRepository->findById($expertId) === null) {
            $form->addError('Vybraný odborník není platný. Obnov prosím formulář a zkus to znovu.');
            return;
        }

        try {
            $eventDate = new DateTimeImmutable((string) $values['date']);
        } catch (\Exception) {
            $form->addError('Zadané datum není platné.');
            return;
        }

        $filePath = $existingEvent?->filePath;

        /** @var FileUpload|null $attachment */
        $attachment = $values['attachment'] ?? null;
        if ($attachment instanceof FileUpload && $attachment->hasFile()) {
            if (!$attachment->isOk()) {
                $form->addError($this->getAttachmentUploadErrorMessage($attachment));
                return;
            }

            $contentType = strtolower((string) $attachment->getContentType());
            $originalName = method_exists($attachment, 'getUntrustedName')
                ? (string) $attachment->getUntrustedName()
                : '';
            $originalExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $extension = self::ATTACHMENT_MIME_MAP[$contentType]
                ?? self::ATTACHMENT_EXTENSION_MAP[$originalExtension]
                ?? null;

            if ($extension === null) {
                $form->addError('Povolené jsou jen PDF a obrázky JPG, PNG, WEBP nebo GIF.');
                return;
            }

            if ($filePath !== null) {
                $this->deleteUploadedFile($filePath);
            }

            $filePath = Random::generate(16) . '.' . $extension;
            $attachment->move($this->uploadDir . '/' . $filePath);
        } elseif (!empty($values['removeAttachment'])) {
            if ($filePath !== null) {
                $this->deleteUploadedFile($filePath);
            }
            $filePath = null;
        }

        $event = new Event(
            name: (string) $values['name'],
            description: (string) $values['description'],
            organiser: (string) $values['organiser'],
            id: $eventId,
            organiserId: $existingEvent?->organiserId,
            status: Event::STATUS_APPROVED,
            date: $eventDate,
            imagePath: $existingEvent?->imagePath,
            filePath: $filePath,
            eventReportPath: $existingEvent?->eventReportPath,
            audienceRoles: $audienceRoles,
            areaTags: $areaTags,
            categoryTag: $categoryTag,
            expertId: $expertId > 0 ? $expertId : null,
            tags: $existingEvent?->tags ?? [],
        );

        $this->eventApplicationService->saveEvent($event);
        $this->flashMessage('Akce byla uložena.', 'success');
        $this->redirect('default');
    }

    public function renderEdit(?int $id = null): void
    {
        if ($id === null) {
            $this->redirect('default');
        }

        $event = $id !== null ? $this->eventRepository->findById($id) : null;
        if ($id !== null && $event === null) {
            $this->error('Akce nebyla nalezena.');
        }

        if ($event !== null) {
            $this['eventForm']->setDefaults([
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'organiser' => $event->organiser,
                'audienceRoles' => $event->getAudienceRoles(),
                'categoryTag' => $event->getCategoryTag(),
                'areaTags' => $event->getAreaTags(),
                'expertId' => $event->expertId ?? 0,
                'date' => $event->date?->format('Y-m-d H:i'),
                'removeAttachment' => false,
            ]);
        }

        $this->template->event = $event;
    }

    /**
     * @return array<string, string>
     */
    private function getTagOptionsByType(string $type): array
    {
        $options = [];
        $scopes = [Tag::SCOPE_EVENT];

        foreach ($this->tagRepository->findActiveByType($type, $scopes) as $tag) {
            $options[$tag->name] = $tag->name;
        }

        return $options;
    }

    private function getAttachmentUploadErrorMessage(FileUpload $attachment): string
    {
        return match ($attachment->getError()) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký. Nahraj prosím soubor do 8 MB.',
            \UPLOAD_ERR_PARTIAL => 'Soubor se nahrál jen částečně. Zkus to prosím znovu.',
            \UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný soubor.',
            default => 'Soubor se nepodařilo nahrát.',
        };
    }

    private function deleteUploadedFile(string $fileName): void
    {
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
