<?php
declare(strict_types=1);

namespace App\Presentation\AdminReports;

use App\Model\Entities\Event;
use App\Model\Reports\EventReportData;
use App\Model\Repositories\IEventRepository;
use App\Model\Services\EventApplicationService;
use App\Model\Services\EventReportService;
use App\Presentation\BaseAdminPresenter;
use DateTimeImmutable;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use RuntimeException;

final class AdminReportsPresenter extends BaseAdminPresenter
{
    private ?Event $selectedEvent = null;

    private ?EventReportData $selectedReport = null;

    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly EventApplicationService $eventApplicationService,
        private readonly EventReportService $eventReportService,
    ) {
        parent::__construct();
    }

    public function renderDefault(?int $eventId = null): void
    {
        $events = $this->getEligibleEvents();
        $reportStates = [];
        foreach ($events as $event) {
            if ($event->id === null) {
                continue;
            }

            $reportStates[$event->id] = $this->eventReportService->loadReport($event->eventReportPath)?->hasContent() ?? false;
        }

        $this->selectedEvent = $eventId !== null ? $this->findEligibleEvent($eventId, $events) : null;
        $this->selectedReport = $this->selectedEvent !== null
            ? $this->eventReportService->loadReport($this->selectedEvent->eventReportPath)
            : null;

        $this->template->events = $events;
        $this->template->reportStates = $reportStates;
        $this->template->selectedEvent = $this->selectedEvent;
        $this->template->selectedReport = $this->selectedReport;
        $this->template->monthFilters = $this->buildMonthFilterOptions();
        $this->template->adminActionToken = $this->getAdminActionToken();

        if ($this->selectedEvent !== null) {
            $this['reportForm']->setDefaults([
                'eventId' => $this->selectedEvent->id,
                'text' => $this->selectedReport?->text ?? '',
            ]);
        }
    }

    protected function createComponentReportForm(): Form
    {
        $form = new Form();
        $form->addProtection('Platnost formuláře vypršela, zkuste to prosím znovu.');

        $form->addHidden('eventId');
        $form->addTextArea('text', 'Popis reportu')
            ->setHtmlAttribute('rows', 6)
            ->setRequired(false);

        $form->addUpload('pdf', 'PDF report')
            ->setRequired(false)
            ->setHtmlAttribute('accept', '.pdf,application/pdf');

        $form->addMultiUpload('images', 'Obrázky')
            ->setRequired(false)
            ->setHtmlAttribute('multiple', true)
            ->setHtmlAttribute('accept', '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp');

        $form->addSubmit('send', 'Uložit report');
        $form->onSuccess[] = [$this, 'reportFormSucceeded'];

        return $form;
    }

    public function reportFormSucceeded(Form $form, array $values): void
    {
        $eventId = isset($values['eventId']) ? (int) $values['eventId'] : 0;
        $event = $this->findEligibleEvent($eventId);
        if ($event === null) {
            $form->addError('Vybraná akce neexistuje nebo k ní nelze nahrát report.');
            return;
        }

        /** @var FileUpload|null $pdfUpload */
        $pdfUpload = $values['pdf'] ?? null;
        /** @var array<int, FileUpload> $imageUploads */
        $imageUploads = is_array($values['images'] ?? null) ? $values['images'] : [];

        try {
            $storedReport = $this->eventReportService->saveReport(
                $event,
                (string) ($values['text'] ?? ''),
                $pdfUpload,
                $imageUploads,
            );
        } catch (RuntimeException $e) {
            $form->addError($e->getMessage());
            return;
        }

        $event->eventReportPath = $storedReport->directory;
        $this->eventApplicationService->saveEvent($event);

        $this->flashMessage('Report byl úspěšně uložen.', 'success');
        $this->redirect('default', ['eventId' => $eventId]);
    }

    #[NoReturn]
    public function handleDeletePdf(int $eventId): void
    {
        $this->assertValidAdminActionRequest();
        $event = $this->findEligibleEvent($eventId);
        if ($event === null) {
            $this->flashMessage('Vybraná akce neexistuje nebo k ní nelze upravit report.', 'warning');
            $this->redirect('default');
        }

        $report = $this->eventReportService->loadReport($event->eventReportPath);
        if ($report === null || $report->pdfEntry === null) {
            $this->flashMessage('PDF report nebyl nalezen.', 'warning');
            $this->redirect('default', ['eventId' => $eventId]);
        }

        try {
            $storedReport = $this->eventReportService->saveReport(
                $event,
                $report->text,
                null,
                [],
                true,
            );
        } catch (RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'warning');
            $this->redirect('default', ['eventId' => $eventId]);
        }

        $event->eventReportPath = $storedReport->directory;
        $this->eventApplicationService->saveEvent($event);

        $this->flashMessage('PDF report byl odebrán.', 'success');
        $this->redirect('default', ['eventId' => $eventId]);
    }

    #[NoReturn]
    public function handleDeleteImage(int $eventId, string $image): void
    {
        $this->assertValidAdminActionRequest();
        $event = $this->findEligibleEvent($eventId);
        if ($event === null) {
            $this->flashMessage('Vybraná akce neexistuje nebo k ní nelze upravit report.', 'warning');
            $this->redirect('default');
        }

        $report = $this->eventReportService->loadReport($event->eventReportPath);
        if ($report === null || !in_array($image, $report->imageEntries, true)) {
            $this->flashMessage('Vybraný obrázek nebyl nalezen.', 'warning');
            $this->redirect('default', ['eventId' => $eventId]);
        }

        try {
            $storedReport = $this->eventReportService->saveReport(
                $event,
                $report->text,
                null,
                [],
                false,
                [$image],
            );
        } catch (RuntimeException $e) {
            $this->flashMessage($e->getMessage(), 'warning');
            $this->redirect('default', ['eventId' => $eventId]);
        }

        $event->eventReportPath = $storedReport->directory;
        $this->eventApplicationService->saveEvent($event);

        $this->flashMessage('Obrázek byl odebrán.', 'success');
        $this->redirect('default', ['eventId' => $eventId]);
    }

    /**
     * @return Event[]
     */
    private function getEligibleEvents(): array
    {
        $now = new DateTimeImmutable();
        $events = array_values(array_filter(
            $this->eventRepository->findApproved(),
            static fn(Event $event): bool => $event->date !== null && $event->date < $now
        ));

        usort($events, static function (Event $a, Event $b): int {
            return ($b->date?->getTimestamp() ?? 0) <=> ($a->date?->getTimestamp() ?? 0);
        });

        return $events;
    }

    /**
     * @param Event[]|null $events
     */
    private function findEligibleEvent(int $eventId, ?array $events = null): ?Event
    {
        foreach ($events ?? $this->getEligibleEvents() as $event) {
            if ($event->id === $eventId) {
                return $event;
            }
        }

        return null;
    }

    private function getAdminActionToken(): string
    {
        $section = $this->getSession('adminReports');
        if (!isset($section->actionToken) || !is_string($section->actionToken) || $section->actionToken === '') {
            $section->actionToken = bin2hex(random_bytes(32));
        }

        return $section->actionToken;
    }

    private function assertValidAdminActionRequest(): void
    {
        if (!$this->getHttpRequest()->isMethod('post')) {
            $this->error('Neplatná metoda požadavku.', 405);
        }

        $token = (string) $this->getHttpRequest()->getPost('_token');
        if (!hash_equals($this->getAdminActionToken(), $token)) {
            $this->error('Neplatný bezpečnostní token.', 403);
        }
    }
}
