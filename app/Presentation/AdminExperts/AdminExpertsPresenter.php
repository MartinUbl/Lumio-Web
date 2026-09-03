<?php
declare(strict_types=1);

namespace App\Presentation\AdminExperts;

use App\Model\Entities\Expert;
use App\Model\Entities\Tag;
use App\Model\Repositories\IExpertRepository;
use App\Model\Repositories\ITagRepository;
use App\Presentation\BaseAdminPresenter;
use JetBrains\PhpStorm\NoReturn;
use Nette\Application\UI\Form;

final class AdminExpertsPresenter extends BaseAdminPresenter
{
    public function __construct(
        private readonly IExpertRepository $expertRepository,
        private readonly ITagRepository $tagRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $experts = $this->expertRepository->findAll();
        usort($experts, static fn(Expert $a, Expert $b): int => strcasecmp($a->name, $b->name));

        $this->template->experts = $experts;
        $this->template->adminActionToken = $this->getAdminActionToken();
    }

    public function renderEdit(?int $id = null): void
    {
        $expert = $id !== null ? $this->expertRepository->findById($id) : null;
        if ($id !== null && $expert === null) {
            $this->error('Odborník nebyl nalezen.');
        }

        if ($expert !== null) {
            $this['expertForm']->setDefaults([
                'id' => $expert->id,
                'name' => $expert->degree !== '' ? $expert->degree . '. ' . $expert->name : $expert->name,
                'institution' => $expert->institution,
                'address' => $expert->address,
                'email' => $expert->email,
                'phone' => $expert->phone,
                'note' => $expert->note,
                'tags' => $expert->tags,
            ]);
        }

        $this->template->expert = $expert;
    }

    protected function createComponentExpertForm(): Form
    {
        $form = new Form();
        //$form->addProtection('Platnost formuláře vypršela, zkuste to prosím znovu.');
        $form->addHidden('id');

        $form->addText('name', 'Jméno a titul')
            ->setRequired('Vyplňte jméno odborníka.');

        $form->addText('institution', 'Instituce');
        $form->addText('address', 'Adresa');
        $form->addText('email', 'E-mail')
            ->addCondition(Form::FILLED)
            ->addRule(Form::EMAIL, 'Zadejte platný e-mail.');
        $form->addText('phone', 'Telefon');
        $form->addTextArea('note', 'Poznámka')
            ->setHtmlAttribute('rows', 5);
        $form->addMultiSelect('tags', 'Obory', $this->getTagOptionsByType(Tag::TYPE_AREA))
            ->setRequired('Vyberte alespoň jeden obor.')
            ->setHtmlAttribute('size', 8);

        $form->addSubmit('send', 'Uložit');
        $form->onSuccess[] = [$this, 'expertFormSucceeded'];

        return $form;
    }

    #[NoReturn]
    public function expertFormSucceeded(Form $form, array $values): void
    {
        $id = isset($values['id']) && $values['id'] !== '' ? (int) $values['id'] : null;
        $existingExpert = $id !== null ? $this->expertRepository->findById($id) : null;
        $selectedTags = array_values(array_unique(array_map('strval', $values['tags'] ?? [])));
        if ($selectedTags === []) {
            $form->addError('Vyberte alespoň jeden obor.');
            return;
        }

        $allowedTags = array_keys($this->getTagOptionsByType(Tag::TYPE_AREA));
        if (array_diff($selectedTags, $allowedTags) !== []) {
            $form->addError('Vybrané obory nejsou platné. Obnov prosím formulář a zkus to znovu.');
            return;
        }

        $expert = new Expert(
            degree: '',
            name: trim((string) $values['name']),
            id: $id,
            institution: $this->normalizeNullableString($values['institution'] ?? null),
            address: $this->normalizeNullableString($values['address'] ?? null),
            email: $this->normalizeNullableString($values['email'] ?? null),
            phone: $this->normalizeNullableString($values['phone'] ?? null),
            note: $this->normalizeNullableString($values['note'] ?? null),
            tags: $selectedTags,
            active: $existingExpert?->active ?? true,
        );

        $this->expertRepository->save($expert);
        $this->flashMessage('Odborník byl uložen.', 'success');
        $this->redirect('default');
    }

    #[NoReturn]
    public function handleHide(int $id): void
    {
        $this->assertValidAdminActionRequest();
        $this->expertRepository->hide($id);
        $this->flashMessage('Odborník byl skryt.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleShow(int $id): void
    {
        $this->assertValidAdminActionRequest();
        $this->expertRepository->show($id);
        $this->flashMessage('Odborník je znovu viditelný.', 'success');
        $this->redirect('this');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    private function getTagOptionsByType(string $type): array
    {
        $options = [];

        foreach ($this->tagRepository->findActiveByType($type, [Tag::SCOPE_EXPERT]) as $tag) {
            $options[$tag->name] = $tag->name;
        }

        return $options;
    }

    private function getAdminActionToken(): string
    {
        $section = $this->getSession('adminExperts');
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
