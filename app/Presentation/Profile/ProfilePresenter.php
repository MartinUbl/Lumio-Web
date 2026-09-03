<?php
declare(strict_types=1);

namespace App\Presentation\Profile;

use App\Model\Entities\Event;
use App\Model\Entities\User;
use App\Model\Repositories\IAttendanceRepository;
use App\Model\Repositories\IEventRepository;
use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;

final class ProfilePresenter extends BasePresenter
{
    public function __construct(
        private readonly IEventRepository $eventRepository,
        private readonly IAttendanceRepository $attendanceRepository,
    ) {
        parent::__construct();
    }

    public function actionDefault(): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro zobrazení profilu se musíš přihlásit.', 'warning');
            $this->redirect('Home:');
        }
    }

    public function renderDefault(): void
    {
        $userId = $this->getUser()->getId();
        $profile = $userId !== null ? $this->userRepository->findById((int) $userId) : null;
        if ($profile === null) {
            $this->flashMessage('Profil se nepodařilo načíst.', 'error');
            $this->redirect('Home:');
        }

        $profileRole = $this->normalizeRoleForUi($profile->role);
        $profileFaculty = $this->roleRequiresFaculty($profileRole)
            ? $this->normalizeFacultyValue($profile->faculty)
            : null;

        $this['profileForm']->setDefaults([
            'name' => $profile->name,
            'email' => $profile->email,
            'role' => $profileRole,
            'faculty' => $profileFaculty,
        ]);

        $allEvents = $this->eventRepository->findAll();
        $suggestedEvents = array_values(array_filter(
            $allEvents,
            fn(Event $event): bool => $this->isSuggestedByProfile($event, $profile)
        ));

        usort($suggestedEvents, static function (Event $a, Event $b): int {
            return ($b->date?->getTimestamp() ?? 0) <=> ($a->date?->getTimestamp() ?? 0);
        });

        $attendedEvents = array_values(array_filter(
            array_map(
                fn(int $eventId): ?Event => $this->eventRepository->findById($eventId),
                $this->attendanceRepository->findEventIdsByUser((int) $profile->id)
            ),
            static fn(?Event $event): bool => $event instanceof Event
        ));

        usort($attendedEvents, static function (Event $a, Event $b): int {
            return ($b->date?->getTimestamp() ?? 0) <=> ($a->date?->getTimestamp() ?? 0);
        });

        $this->template->profile = $profile;
        $this->template->profileRoleLabel = $this->getRoleLabel($profile->role);
        $this->template->profileFaculty = $profileFaculty;
        $this->template->showFacultyField = $this->roleRequiresFaculty($profileRole);
        $this->template->suggestedEvents = $suggestedEvents;
        $this->template->attendedEvents = $attendedEvents;
        $this->template->suggestedCount = count($suggestedEvents);
        $this->template->attendedCount = count($attendedEvents);
    }

    protected function createComponentProfileForm(): Form
    {
        $form = new Form();
        $form->addText('name', 'Jméno')
            ->setRequired('Vyplň své jméno.');
        $form->addText('email', 'E-mail')
            ->setRequired('Vyplň e-mail.')
            ->addRule($form::Email, 'Neplatný formát e-mailu.');
        $form->addSelect('role', 'Role', $this->getRoleOptions())
            ->setRequired('Vyber roli.')
            ->setHtmlAttribute('id', 'profile-role-select');
        $form->addSelect('faculty', 'Fakulta', $this->getFacultyOptions())
            ->setPrompt('Vyber fakultu')
            ->setHtmlAttribute('id', 'profile-faculty-select');
        $form->addSubmit('send', 'Uložit změny');
        $form->onSuccess[] = [$this, 'profileFormSucceeded'];

        return $form;
    }

    public function profileFormSucceeded(Form $form, array $values): void
    {
        $userId = $this->getUser()->getId();
        $currentUser = $userId !== null ? $this->userRepository->findById((int) $userId) : null;
        if ($currentUser === null) {
            $this->flashMessage('Profil se nepodařilo uložit.', 'error');
            $this->redirect('this');
        }

        $existingByEmail = $this->userRepository->findByEmail((string) $values['email']);
        if ($existingByEmail !== null && $existingByEmail->id !== $currentUser->id) {
            $form->addError('Uživatel s tímto e-mailem už existuje.');

            return;
        }

        $selectedRole = (string) $values['role'];
        $faculty = $this->roleRequiresFaculty($selectedRole)
            ? $this->normalizeFacultyValue($values['faculty'] ?? null)
            : null;

        $updatedUser = new User(
            name: (string) $values['name'],
            email: (string) $values['email'],
            passwordHash: $currentUser->passwordHash,
            id: $currentUser->id,
            role: $selectedRole,
            faculty: $faculty,
            active: $currentUser->active,
            tags: $currentUser->tags,
            attendedEventIds: $currentUser->attendedEventIds,
            admin: $currentUser->admin,
        );

        $this->userRepository->save($updatedUser);
        $this->getUser()->login($this->createIdentity($updatedUser));

        $this->flashMessage('Profil byl úspěšně aktualizován.', 'success');
        $this->redirect('this');
    }

    private function isSuggestedByProfile(Event $event, User $profile): bool
    {
        if ($event->organiserId !== null) {
            return $event->organiserId === $profile->id;
        }

        return $this->normalizeText($event->organiser) === $this->normalizeText($profile->name);
    }

    private function normalizeText(?string $value): string
    {
        $value = trim((string) $value);

        return function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);
    }
}
