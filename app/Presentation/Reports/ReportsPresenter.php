<?php
declare(strict_types=1);

namespace App\Presentation\Reports;

use App\Model\Entities\Event;
use App\Model\Services\EventReportService;
use App\Model\Repositories\IEventRepository;
use App\Presentation\BasePresenter;

final class ReportsPresenter extends BasePresenter
{
    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly EventReportService $eventReportService,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $reports = [];
        foreach ($this->eventRepository->findApproved() as $event) {
            $report = $this->eventReportService->loadReport($event->eventReportPath);
            if ($report === null || !$report->hasContent()) {
                continue;
            }

            $reports[] = [
                'event' => $event,
                'report' => $report,
            ];
        }

        usort($reports, static function (array $a, array $b): int {
            /** @var Event $eventA */
            $eventA = $a['event'];
            /** @var Event $eventB */
            $eventB = $b['event'];

            return ($eventB->date?->getTimestamp() ?? 0) <=> ($eventA->date?->getTimestamp() ?? 0);
        });

        $this->template->reports = $reports;
    }
}
