<?php
declare(strict_types=1);

namespace App\Presentation\AdminTags;

use App\Model\Entities\Tag;
use App\Model\Repositories\ITagRepository;
use App\Presentation\BaseAdminPresenter;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Form;

final class AdminTagsPresenter extends BaseAdminPresenter
{
    /** @var array<string, string> */
    private const array TYPE_OPTIONS = [
        Tag::TYPE_AREA => 'Obor',
        Tag::TYPE_CATEGORY => 'Kategorie',
    ];

    /** @var array<string, string> */
    private const array SCOPE_OPTIONS = [
        Tag::SCOPE_EVENT => 'Jen akce',
        Tag::SCOPE_EXPERT => 'Jen odborníci',
        Tag::SCOPE_BOTH => 'Akce i odborníci',
    ];

    public function __construct(
        private readonly ITagRepository $tagRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(?int $editId = null): void
    {
        $tags = $this->tagRepository->findAll();
        $usage = [];

        foreach ($tags as $tag) {
            if ($tag->id === null) {
                continue;
            }

            $usage[$tag->id] = $this->tagRepository->getUsageCounts($tag->id);
        }

        $this->template->eventAreaTags = array_values(array_filter(
            $tags,
            static fn(Tag $tag): bool => $tag->type === Tag::TYPE_AREA
                && in_array($tag->scope, [Tag::SCOPE_EVENT, Tag::SCOPE_BOTH], true)
        ));
        $this->template->eventCategoryTags = array_values(array_filter(
            $tags,
            static fn(Tag $tag): bool => $tag->type === Tag::TYPE_CATEGORY
                && $tag->scope === Tag::SCOPE_EVENT
        ));
        $this->template->expertAreaTags = array_values(array_filter(
            $tags,
            static fn(Tag $tag): bool => $tag->type === Tag::TYPE_AREA
                && in_array($tag->scope, [Tag::SCOPE_EXPERT, Tag::SCOPE_BOTH], true)
        ));
        $this->template->tagUsage = $usage;
        $this->template->adminActionToken = $this->getAdminActionToken();
        $this->template->editingTag = $editId !== null ? $this->tagRepository->findById($editId) : null;
        $this->template->typeOptions = self::TYPE_OPTIONS;
        $this->template->scopeOptions = self::SCOPE_OPTIONS;

        if ($editId !== null && $this->template->editingTag instanceof Tag) {
            $this['tagForm']->setDefaults([
                'id' => $this->template->editingTag->id,
                'name' => $this->template->editingTag->name,
                'type' => $this->template->editingTag->type,
                'scope' => $this->template->editingTag->scope,
                'isActive' => $this->template->editingTag->isActive,
            ]);
        }
    }

    protected function createComponentTagForm(): Form
    {
        $form = new Form();
        $form->getElementPrototype()->addClass('auth-form-shell tag-form-shell');
        $form->addProtection('Platnost formuláře vypršela, zkuste to prosím znovu.');
        $form->addHidden('id');

        $form->addText('name', 'Název tagu')
            ->setRequired('Vyplňte název tagu.')
            ->setHtmlAttribute('placeholder', 'Např. Programování');

        $form->addSelect('type', 'Typ tagu', self::TYPE_OPTIONS)
            ->setRequired('Vyberte typ tagu.');

        $form->addSelect('scope', 'Použití tagu', self::SCOPE_OPTIONS)
            ->setRequired('Vyberte, kde se tag používá.');

        $form->addCheckbox('isActive', 'Tag je aktivní')
            ->setDefaultValue(true);

        $form->addSubmit('send', 'Uložit tag')
            ->setHtmlAttribute('class', 'btn-submit');
        $form->onSuccess[] = [$this, 'tagFormSucceeded'];

        return $form;
    }

    #[NoReturn]
    public function tagFormSucceeded(Form $form, array $values): void
    {
        $id = isset($values['id']) && $values['id'] !== '' ? (int) $values['id'] : null;
        $existingTag = $id !== null ? $this->tagRepository->findById($id) : null;
        if ($id !== null && $existingTag === null) {
            $this->flashMessage('Tag nebyl nalezen.', 'warning');
            $this->redirect('default');
        }

        $type = (string) ($values['type'] ?? '');
        $scope = (string) ($values['scope'] ?? '');
        $name = trim((string) ($values['name'] ?? ''));
        $isActive = !empty($values['isActive']);

        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $form->addError('Vybraný typ tagu není platný.');
            return;
        }

        if (!array_key_exists($scope, self::SCOPE_OPTIONS)) {
            $form->addError('Vybrané použití tagu není platné.');
            return;
        }

        if ($type === Tag::TYPE_CATEGORY && $scope !== Tag::SCOPE_EVENT) {
            $form->addError('Kategorie lze používat jen u akcí.');
            return;
        }

        if ($existingTag instanceof Tag && $existingTag->id !== null) {
            $usage = $this->tagRepository->getUsageCounts($existingTag->id);
            $isUsed = $usage['events'] > 0 || $usage['experts'] > 0;

            if ($isUsed && ($existingTag->type !== $type || $existingTag->scope !== $scope)) {
                $form->addError('U použitého tagu lze změnit jen název a aktivitu. Typ a použití musí zůstat stejné.');
                return;
            }
        }

        try {
            $this->tagRepository->save(new Tag(
                name: $name,
                slug: Tag::slugify($name),
                type: $type,
                scope: $scope,
                isActive: $isActive,
                id: $id,
            ));
        } catch (\InvalidArgumentException $e) {
            $form->addError($e->getMessage());
            return;
        }

        $this->flashMessage($id === null ? 'Tag byl přidán.' : 'Tag byl upraven.', 'success');
        $this->redirect('default');
    }

    #[NoReturn]
    public function handleActivate(int $id): void
    {
        $this->assertValidAdminActionRequest();
        $tag = $this->tagRepository->findById($id);
        if ($tag === null) {
            $this->flashMessage('Tag nebyl nalezen.', 'warning');
            $this->redirect('this');
        }

        $this->tagRepository->setActive($id, true);
        $this->flashMessage('Tag byl znovu aktivován.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleDeactivate(int $id): void
    {
        $this->assertValidAdminActionRequest();
        $tag = $this->tagRepository->findById($id);
        if ($tag === null) {
            $this->flashMessage('Tag nebyl nalezen.', 'warning');
            $this->redirect('this');
        }

        $this->tagRepository->setActive($id, false);
        $this->flashMessage('Tag byl deaktivován.', 'success');
        $this->redirect('this');
    }


    private function getAdminActionToken(): string
    {
        $section = $this->getSession('adminTags');
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
