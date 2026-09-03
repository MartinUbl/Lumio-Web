<?php
declare(strict_types=1);

namespace App\Presentation;

use App\Model\Entities\User;
use App\Model\Repositories\UserRepository;
use App\Model\Security\UserAuthenticator;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

abstract class BasePresenter extends Presenter
{
    /** @inject */
    public UserAuthenticator $authenticator;

    /** @inject */
    public UserRepository $userRepository;

    /** @inject */
    public Passwords $passwords;

    protected bool $authModalOpen = false;

    protected string $authModalTab = 'login';

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->user = $this->getUser();
        $this->template->canAccessAdmin = $this->isCurrentUserAdmin();
        $this->template->authModalOpen = $this->authModalOpen;
        $this->template->authModalTab = $this->authModalTab;
    }

    protected function openAuthModal(string $tab): void
    {
        $this->authModalOpen = true;
        $this->authModalTab = $tab;
    }

    public function isUserLoggedIn(): bool
    {
        return $this->getUser()->isLoggedIn();
    }

    public function getCurrentUser(): ?\Nette\Security\User
    {
        return $this->getUser()->isLoggedIn() ? $this->getUser() : null;
    }

    /**
     * @return array<string, string>
     */
    protected function getRoleOptions(): array
    {
        return [
            'student' => 'Student ZČU',
            'absolvent' => 'Absolvent ZČU',
            'zamestnanec' => 'Zaměstnanec ZČU',
            'stredoskolak' => 'Žák SŠ',
            'jine' => 'Ostatní',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getFacultyOptions(): array
    {
        return [
            'FAV' => 'Fakulta aplikovaných věd',
            'FDU' => 'Fakulta designu a umění Ladislava Sutnara',
            'FEK' => 'Fakulta ekonomická',
            'FEL' => 'Fakulta elektrotechnická',
            'FF' => 'Fakulta filozofická',
            'FPE' => 'Fakulta pedagogická',
            'FPR' => 'Fakulta právnická',
            'FST' => 'Fakulta strojní',
            'FZS' => 'Fakulta zdravotnických studií',
        ];
    }

    protected function normalizeRoleForUi(?string $role): string
    {
        $normalized = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $role)
            : (string) $role;

        $normalized = strtolower($normalized);
        $normalized = preg_replace('~[^a-z0-9]+~', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return match ($normalized) {
            'student' => 'student',
            'absolvent' => 'absolvent',
            'zamestnanec', 'zamestnanec-zcu' => 'zamestnanec',
            'stredoskolak', 'zak-ss' => 'stredoskolak',
            'jine', 'ostatni' => 'jine',
            default => 'student',
        };
    }

    protected function getRoleLabel(?string $role): string
    {
        $normalizedRole = $this->normalizeRoleForUi($role);

        return $this->getRoleOptions()[$normalizedRole] ?? $normalizedRole;
    }

    protected function roleRequiresFaculty(?string $role): bool
    {
        $normalizedRole = $this->normalizeRoleForUi($role);

        return in_array($normalizedRole, ['student', 'absolvent'], true);
    }

    protected function normalizeFacultyValue(?string $faculty): ?string
    {
        $faculty = trim((string) $faculty);
        if ($faculty === '') {
            return null;
        }

        return array_key_exists($faculty, $this->getFacultyOptions()) ? $faculty : null;
    }

    protected function isCurrentUserAdmin(): bool
    {
        if (!$this->getUser()->isLoggedIn()) {
            return false;
        }

        $identity = $this->getUser()->getIdentity();
        if ($identity instanceof SimpleIdentity) {
            $data = $identity->getData();
            if (array_key_exists('admin', $data)) {
                return (bool) $data['admin'];
            }
        }

        $userId = $this->getUser()->getId();
        if ($userId === null) {
            return false;
        }

        return $this->userRepository->findById((int) $userId)?->admin ?? false;
    }

    protected function getCurrentUserPublicRole(): ?string
    {
        if (!$this->getUser()->isLoggedIn()) {
            return null;
        }

        $identity = $this->getUser()->getIdentity();
        if ($identity instanceof SimpleIdentity) {
            $data = $identity->getData();
            if (isset($data['publicRole'])) {
                return $this->normalizeRoleForUi((string) $data['publicRole']);
            }
        }

        $userId = $this->getUser()->getId();
        if ($userId === null) {
            return null;
        }

        $user = $this->userRepository->findById((int) $userId);

        return $user !== null ? $this->normalizeRoleForUi($user->role) : null;
    }

    protected function createIdentity(User $user): SimpleIdentity
    {
        return new SimpleIdentity(
            id: $user->id,
            roles: [$user->role],
            data: [
                'name' => $user->name,
                'email' => $user->email,
                'publicRole' => $user->role,
                'faculty' => $user->faculty,
                'tags' => $user->tags,
                'attendedEventIds' => $user->attendedEventIds,
                'admin' => $user->admin,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function buildMonthFilterOptions(): array
    {
        $options = [];
        for ($month = 1; $month <= 12; $month++) {
            $value = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $options[$value] = $this->getCzechMonthName($month);
        }

        return $options;
    }

    protected function getCzechMonthName(int $month): string
    {
        return match ($month) {
            1 => 'Leden',
            2 => 'Únor',
            3 => 'Březen',
            4 => 'Duben',
            5 => 'Květen',
            6 => 'Červen',
            7 => 'Červenec',
            8 => 'Srpen',
            9 => 'Září',
            10 => 'Říjen',
            11 => 'Listopad',
            12 => 'Prosinec',
            default => 'Neznámý měsíc',
        };
    }

    protected function createComponentSignInForm(): Form
    {
        $form = new Form();
        $form->addText('email', 'E-mail')
            ->setRequired('Zadejte e-mail.')
            ->addRule($form::Email, 'Neplatný formát e-mailu.');

        $form->addPassword('password', 'Heslo')
            ->setRequired('Zadejte heslo.');

        $form->addSubmit('send', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'signInFormSucceeded'];
        $form->onError[] = function (): void {
            $this->openAuthModal('login');
        };

        return $form;
    }

    public function signInFormSucceeded(Form $form, array $values): void
    {
        try {
            $identity = $this->authenticator->authenticate(
                trim((string) $values['email']),
                (string) $values['password'],
            );
            $this->getUser()->login($identity);

            $this->flashMessage('Přihlášení proběhlo úspěšně.', 'success');
            $this->redirect('this');
        } catch (AuthenticationException $e) {
            $form->addError($e->getMessage());
            $this->openAuthModal('login');
        }
    }

    protected function createComponentSignUpForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Jméno')
            ->setRequired('Zadejte své jméno.');

        $form->addText('email', 'E-mail')
            ->setRequired('Zadejte e-mail.')
            ->addRule($form::Email, 'Neplatný formát e-mailu.');

        $form->addPassword('password', 'Heslo')
            ->setRequired('Zadejte heslo.')
            ->addRule($form::MinLength, 'Heslo musí mít alespoň %d znaků.', 6);

        $form->addPassword('passwordVerify', 'Potvrzení hesla')
            ->setRequired('Zadejte heslo znovu pro kontrolu.')
            ->addRule($form::Equal, 'Hesla se neshodují.', $form['password']);

        $form->addSelect('role', 'Vyber, co se na tebe hodí nejvíc', $this->getRoleOptions())
            ->setDefaultValue('student')
            ->setRequired('Vyberte roli.')
            ->setHtmlAttribute('id', 'role-select');

        $facultyContainer = $form->addContainer('facultyContainer');
        $facultyContainer->addSelect('faculty', 'Vyber svou fakultu', $this->getFacultyOptions())
            ->setPrompt('-- Vyberte fakultu --')
            ->setHtmlAttribute('id', 'faculty-select');

        $form->addSubmit('send', 'Registrovat');
        $form->onSuccess[] = [$this, 'signUpFormSucceeded'];
        $form->onError[] = function (): void {
            $this->openAuthModal('register');
        };

        return $form;
    }

    public function signUpFormSucceeded(Form $form, array $values): void
    {
        if ($this->userRepository->findByEmail($values['email'])) {
            $form->addError('Uživatel s tímto e-mailem už existuje.');

            $this->openAuthModal('register');
            return;
        }

        $selectedRole = (string) $values['role'];
        $faculty = $this->roleRequiresFaculty($selectedRole)
            ? $this->normalizeFacultyValue($values['facultyContainer']['faculty'] ?? null)
            : null;

        $user = new User(
            name: $values['name'],
            email: $values['email'],
            passwordHash: $this->passwords->hash($values['password']),
            role: $selectedRole,
            faculty: $faculty,
        );

        $this->userRepository->save($user);
        $this->flashMessage('Registrace proběhla úspěšně. Nyní se můžeš přihlásit.', 'success');
        $this->redirect('this');
    }
}
