<?php
declare(strict_types=1);

namespace App\Presentation\Event;

use App\Model\Entities\Event;
use App\Model\Entities\Tag;
use App\Model\Repositories\IAttendanceRepository;
use App\Model\Repositories\IEventRepository;
use App\Model\Repositories\IExpertRepository;
use App\Model\Repositories\ITagRepository;
use App\Model\Services\EventApplicationService;
use App\Model\Services\EventReportService;
use App\Presentation\Accessory\DiscordIntegration;
use App\Presentation\BasePresenter;
use App\Presentation\Components\EventListControl;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Random;

final class EventPresenter extends BasePresenter
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
        private readonly EventReportService $eventReportService,
        private readonly DiscordIntegration $discord,
        private readonly string $uploadDir,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $now = new DateTimeImmutable();
        $events = array_values(array_filter(
            $this->eventRepository->findApproved(),
            static fn(Event $event): bool => $event->date !== null && $event->date >= $now
        ));

        usort($events, static function (Event $a, Event $b): int {
            return ($a->date?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->date?->getTimestamp() ?? PHP_INT_MAX);
        });

        $areaTags = [];
        foreach ($events as $event) {
            foreach ($event->getAreaTags() as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $areaTags[$tag] = $tag;
                }
            }
        }

        natcasesort($areaTags);

        $this->template->events = $events;
        $this->template->linkedExpertNames = $this->buildLinkedExpertNames($events);
        $this->template->monthFilters = $this->buildMonthFilterOptions();
        $this->template->areaTags = array_values($areaTags);
        $this->template->categoryTags = array_map(
            static fn(Tag $tag): string => $tag->name,
            $this->tagRepository->findActiveByType(Tag::TYPE_CATEGORY, [Tag::SCOPE_EVENT]),
        );
    }

    public function renderDetail(int $id): void
    {
        $event = $this->eventRepository->findById($id);
        if (!$event || $event->status !== Event::STATUS_APPROVED) {
            $this->error('Akce nebyla nalezena.', 404);
        }

        $this->template->event = $event;
        $this->template->isAttending = $this->isCurrentUserAttending($event->id);
        $this->template->isPast = $event->date !== null && $event->date < new DateTimeImmutable();
        $this->template->googleCalendarUrl = $this->buildGoogleCalendarUrl($event);
        $this->template->attachmentType = $this->detectAttachmentType($event->filePath);
        $this->template->canAttend = $event->canUserAttend($this->getCurrentUserPublicRole());
        $this->template->audienceLabel = $event->getAudienceSummary();
        $this->template->linkedExpert = $this->findExpertById($event->expertId);
        $this->template->report = $this->eventReportService->loadReport($event->eventReportPath);
    }

    #[NoReturn]
    public function handleAttend(int $id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro přihlášení na akci se musíš přihlásit.', 'warning');
            $this->redirect('this');
        }

        $event = $this->eventRepository->findById($id);
        if ($event === null || $event->status !== Event::STATUS_APPROVED) {
            $this->error('Akce neexistuje.');
        }

        if (!$event->canUserAttend($this->getCurrentUserPublicRole())) {
            $this->flashMessage('Tato akce je určena pro jinou skupinu uživatelů.', 'info');
            $this->redirect('this');
        }

        $userId = $this->getUser()->getId();
        if ($userId === null || $event->id === null) {
            $this->flashMessage('Přihlášení na akci se nepodařilo.', 'error');
            $this->redirect('this');
        }

        if ($this->attendanceRepository->isAttending((int) $userId, $event->id)) {
            $this->flashMessage('Na tuto akci už jsi přihlášen.', 'info');
            $this->redirect('this');
        }

        $this->attendanceRepository->attend((int) $userId, $event->id);

        $this->flashMessage('Byl jsi úspěšně přihlášen na akci.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleLeave(int $id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro odhlášení z akce se musíš přihlásit.', 'warning');
            $this->redirect('this');
        }

        $userId = $this->getUser()->getId();
        if ($userId === null) {
            $this->flashMessage('Odhlášení z akce se nepodařilo.', 'error');
            $this->redirect('this');
        }

        $this->attendanceRepository->leave((int) $userId, $id);

        $this->flashMessage('Byl jsi odhlášen z akce.', 'success');
        $this->redirect('this');
    }

    protected function createComponentEventList(): EventListControl
    {
        return new EventListControl($this->eventRepository, $this->tagRepository);
    }

    public function actionSuggest(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro navržení akce se musíš přihlásit.', 'warning');
            $this->redirect('Home:');
        }
    }

    protected function createComponentSuggestForm(): Form
    {
        $form = new Form();
        $form->addProtection('Platnost formuláře vypršela, zkus to prosím znovu.');

        $identity = $this->getUser()->getIdentity();
        $defaultOrganiser = isset($identity->data['name']) ? (string) $identity->data['name'] : 'Člen komunity';

        $form->addText('name', 'Název akce')
            ->setRequired('Zadejte prosím název akce.');

        $form->addTextArea('description', 'Popis akce')
            ->setRequired('Popište krátce, o čem akce bude.');

        $form->addText('organiser', 'Organizátor')
            ->setDefaultValue($defaultOrganiser)
            ->setRequired('Vyplň organizátora akce.');

        $form->addText('date', 'Datum a čas')
            ->setHtmlType('datetime-local')
            ->setRequired('Vyber termín konání.');

        $form->addMultiSelect('audienceRoles', 'Pro koho je akce určena', self::AUDIENCE_ROLE_OPTIONS)
            ->setRequired('Vyber alespoň jednu cílovou skupinu.')
            ->setHtmlAttribute('size', 5);

        $form->addSelect('categoryTag', 'Kategorie', $this->getTagOptionsByType(Tag::TYPE_CATEGORY))
            ->setPrompt('Vyberte kategorii')
            ->setRequired('Vyber kategorii.');

        $form->addMultiSelect('areaTags', 'Obory', $this->getTagOptionsByType(Tag::TYPE_AREA))
            ->setHtmlAttribute('size', 6);

        $form->addUpload('attachment', 'Soubor k akci')
            ->setRequired(false);

        $form->addSubmit('send', 'Odeslat návrh ke schválení');

        $form->onSuccess[] = [$this, 'suggestFormSucceeded'];

        return $form;
    }

    public function suggestFormSucceeded(Form $form, array $values): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro navržení akce se musíš přihlásit.', 'warning');
            $this->redirect('Home:');
        }

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
        if ($categoryTag !== null && !in_array($categoryTag, $allowedCategoryTags, true)) {
            $form->addError('Vybraná kategorie není platná. Obnov prosím formulář a zkus to znovu.');
            return;
        }

        try {
            $eventDate = new DateTimeImmutable((string) $values['date']);
        } catch (\Exception) {
            $form->addError('Zadané datum není platné.');
            return;
        }

        $uploadedAttachmentPath = null;

        /** @var FileUpload|null $attachment */
        $attachment = $values['attachment'] ?? null;
        if ($attachment instanceof FileUpload && $attachment->hasFile()) {
            if (!$attachment->isOk()) {
                $form->addError('Soubor se nepodařilo nahrát.');
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

            $uploadedAttachmentPath = Random::generate(16) . '.' . $extension;
            $attachment->move($this->uploadDir . '/' . $uploadedAttachmentPath);
        }

        $organiserId = $this->getUser()->getId();

        $event = new Event(
            name: (string) $values['name'],
            description: (string) $values['description'],
            organiser: (string) $values['organiser'],
            organiserId: $organiserId !== null ? (int) $organiserId : null,
            status: Event::STATUS_SUGGESTED,
            date: $eventDate,
            filePath: $uploadedAttachmentPath,
            audienceRoles: $audienceRoles,
            areaTags: $areaTags,
            categoryTag: $categoryTag,
        );

        $this->eventApplicationService->saveEvent($event);
        $this->discord->postEventNotification($event);

        $this->flashMessage('Děkuji, návrh akce byl odeslán ke schválení.', 'success');
        $this->redirect('Event:default');
    }

    private function buildGoogleCalendarUrl(Event $event): ?string
    {
        if ($event->date === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromInterface($event->date)
            ->setTimezone(new DateTimeZone('UTC'));
        $end = $start->add(new DateInterval('PT2H'));

        $details = trim(sprintf(
            "Organizátor: %s\n\n%s",
            $event->organiser,
            $event->description
        ));

        $query = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event->name,
            'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details' => $details,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://calendar.google.com/calendar/render?' . $query;
    }

    private function detectAttachmentType(?string $filePath): ?string
    {
        if ($filePath === null || $filePath === '') {
            return null;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            default => 'file',
        };
    }

    /**
     * @return array<string, string>
     */
    private function getTagOptionsByType(string $type): array
    {
        $options = [];

        foreach ($this->tagRepository->findActiveByType($type, [Tag::SCOPE_EVENT]) as $tag) {
            $options[$tag->name] = $tag->name;
        }

        return $options;
    }

    private function findExpertById(?int $expertId): ?object
    {
        if ($expertId === null) {
            return null;
        }

        return $this->expertRepository->findById($expertId, true);
    }

    private function isCurrentUserAttending(?int $eventId): bool
    {
        if ($eventId === null || !$this->getUser()->isLoggedIn()) {
            return false;
        }

        $userId = $this->getUser()->getId();
        if ($userId === null) {
            return false;
        }

        return $this->attendanceRepository->isAttending((int) $userId, $eventId);
    }

    /**
     * @param Event[] $events
     * @return array<int, string>
     */
    private function buildLinkedExpertNames(array $events): array
    {
        $names = [];

        foreach ($events as $event) {
            if ($event->id === null || $event->expertId === null) {
                continue;
            }

            $expert = $this->findExpertById($event->expertId);
            if ($expert === null) {
                continue;
            }

            $name = trim((string) $expert->name);
            if ($name === '') {
                continue;
            }

            $names[$event->id] = $name;
        }

        return $names;
    }
}
