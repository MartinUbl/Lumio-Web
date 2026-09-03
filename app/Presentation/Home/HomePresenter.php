<?php
declare(strict_types=1);

namespace App\Presentation\Home;

use App\Model\Entities\Event;
use App\Model\Repositories\IEventRepository;
use App\Model\Repositories\IExpertRepository;
use App\Model\Repositories\ITagRepository;
use App\Presentation\BasePresenter;

final class HomePresenter extends BasePresenter
{
    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly IExpertRepository $expertRepository,
        private readonly ITagRepository $tagRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $now = new \DateTimeImmutable();
        $events = array_values(array_filter(
            $this->eventRepository->findApproved(),
            static fn($event) => $event->date !== null && $event->date >= $now
        ));

        usort($events, static function ($a, $b): int {
            return ($a->date?->getTimestamp() ?? PHP_INT_MAX) <=> ($b->date?->getTimestamp() ?? PHP_INT_MAX);
        });

        $upcomingEvents = array_slice($events, 0, 6);
        $areaTags = [];
        $categoryTags = [];
        foreach ($upcomingEvents as $event) {
            foreach ($event->getAreaTags() as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $areaTags[$tag] = $tag;
                }
            }

            $categoryTag = $event->getCategoryTag();
            if ($categoryTag !== null && $categoryTag !== '') {
                $categoryTags[$categoryTag] = $categoryTag;
            }
        }

        natcasesort($areaTags);

        $this->template->discordInviteLink = 'https://discord.gg/XyjpTbCJ76';
        $this->template->infoZcuLink = 'https://info.zcu.cz/';
        $this->template->upcomingEvents = $upcomingEvents;
        $this->template->linkedExpertNames = $this->buildLinkedExpertNames($upcomingEvents);
        $this->template->monthFilters = $this->buildMonthFilterOptions();
        $this->template->areaTags = array_values($areaTags);
        $this->template->categoryTags = array_map(
            static fn($tag): string => $tag->name,
            $this->tagRepository->findActiveByType(\App\Model\Entities\Tag::TYPE_CATEGORY, [\App\Model\Entities\Tag::SCOPE_EVENT]),
        );
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

            $expert = $this->expertRepository->findById($event->expertId, true);
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
