<?php
declare(strict_types=1);

namespace App\Presentation\Expert;

use App\Model\Entities\Expert;
use App\Model\Repositories\IExpertRepository;
use App\Presentation\BasePresenter;

final class ExpertPresenter extends BasePresenter
{
    public function __construct(
        private readonly IExpertRepository $expertRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->template->requiresLogin = true;
            $this->template->experts = [];
            $this->template->expertAreaTags = [];
            return;
        }

        $experts = $this->expertRepository->findAll(true);
        usort($experts, static fn(Expert $a, Expert $b): int => strcasecmp($a->name, $b->name));

        $areaTags = [];
        foreach ($experts as $expert) {
            foreach ($expert->tags as $tag) {
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }

                $areaTags[$tag] = $tag;
            }
        }

        natcasesort($areaTags);

        $this->template->experts = $experts;
        $this->template->expertAreaTags = array_values($areaTags);
        $this->template->requiresLogin = false;
    }
}
