<?php
declare(strict_types=1);

namespace App\Presentation\Components;

use App\Model\Entities\Event;
use App\Model\Repositories\IEventRepository;
use App\Model\Repositories\ITagRepository;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;

final class EventListControl extends Control
{
    private const string DEFAULT_FILTER = 'všechny';

    /** @var string[] */
    private array $activeFilters = [];

    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly ITagRepository $tagRepository,
    ) {
    }

    public function render(): void
    {
        $this->template->events = $this->getFilteredEvents();
        $this->template->allTags = $this->tagRepository->findAll();
        $this->template->activeFilters = $this->activeFilters;
        $this->template->setFile(__DIR__ . '/EventListControl.latte');
        $this->template->render();
    }


    protected function createComponentFilterForm(): Form
    {
        $form = new Form();
        $form->setMethod('GET');

        $tags = array_merge(
            $this->tagRepository->findActiveByType(\App\Model\Entities\Tag::TYPE_CATEGORY, [\App\Model\Entities\Tag::SCOPE_EVENT]),
            $this->tagRepository->findActiveByType(\App\Model\Entities\Tag::TYPE_AREA, [\App\Model\Entities\Tag::SCOPE_EVENT]),
        );
        $tagOptions = [];
        foreach ($tags as $tag) {
            $tagOptions[$tag->name] = $tag->name;
        }

        $form->addMultiSelect('tags', 'Filtrovat podle tagů:', $tagOptions)
            ->setHtmlAttribute('size', 5);

        $form->addSubmit('filter', 'Filtrovat');
        $form->addSubmit('clear', 'Zrušit filtr')
            ->setValidationScope([])
            ->setHtmlAttribute('class', 'btn btn-secondary ms-2');

        $form->onSuccess[] = [$this, 'filterFormSucceeded'];
        return $form;
    }

    public function filterFormSucceeded(Form $form, array $values): void
    {
        if ($form->isSubmitted()->getValue() === 'clear') {
            $this->activeFilters = [];
        } else {
            $this->activeFilters = $values['tags'] ?? [];
        }
        $this->redrawControl('eventList');
    }


    private function getFilteredEvents(): array
    {
        $events = $this->eventRepository->findApproved();

        if (empty($this->activeFilters)) {
            return $events;
        }

        return array_filter($events, function (Event $event) {
            $eventTags = $event->getFilterTags();
            foreach ($this->activeFilters as $filter) {
                if (in_array($filter, $eventTags, true)) {
                    return true;
                }
            }
            return false;
        });
    }
}
