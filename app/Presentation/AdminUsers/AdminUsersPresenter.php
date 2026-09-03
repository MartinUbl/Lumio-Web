<?php declare(strict_types=1);

namespace App\Presentation\AdminUsers;

use App\Model\Entities\User;
use App\Presentation\BaseAdminPresenter;
use JetBrains\PhpStorm\NoReturn;

final class AdminUsersPresenter extends BaseAdminPresenter
{
    public function renderDefault(): void
    {
        $users = $this->userRepository->findAll();
        $roleLabels = [];
        foreach ($users as $user) {
            if ($user->id !== null) {
                $roleLabels[$user->id] = $this->getRoleLabel($user->role);
            }
        }

        $this->template->users = $users;
        $this->template->roleLabels = $roleLabels;
        $this->template->roleOptions = $this->getRoleOptions();
        $this->template->currentUserId = $this->getUser()->getId();
        $this->template->adminActionToken = $this->getAdminActionToken();
    }

    #[NoReturn]
    public function handleBlock(int $id): void
    {
        $this->assertValidAdminActionRequest();

        if ($this->isCurrentUserTarget($id)) {
            $this->flashMessage('Svůj vlastní účet nelze zablokovat.', 'warning');
            $this->redirect('this');
        }

        $this->userRepository->deactivate($id);
        $this->flashMessage('Uživatel byl zablokován.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleUnblock(int $id): void
    {
        $this->assertValidAdminActionRequest();

        if ($this->isCurrentUserTarget($id)) {
            $this->flashMessage('Svůj vlastní účet zde nelze měnit.', 'warning');
            $this->redirect('this');
        }

        $user = $this->userRepository->findById($id);
        if ($user === null) {
            $this->flashMessage('Uživatel nebyl nalezen.', 'error');
            $this->redirect('this');
        }

        $this->userRepository->save(new User(
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            id: $user->id,
            role: $user->role,
            faculty: $user->faculty,
            active: true,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: $user->admin,
        ));

        $this->flashMessage('Uživatel byl odblokován.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleGrantAdmin(int $id): void
    {
        $this->assertValidAdminActionRequest();

        if ($this->isCurrentUserTarget($id)) {
            $this->flashMessage('Vlastní admin oprávnění zde nelze měnit.', 'warning');
            $this->redirect('this');
        }

        $user = $this->userRepository->findById($id);
        if ($user === null) {
            $this->flashMessage('Uživatel nebyl nalezen.', 'error');
            $this->redirect('this');
        }

        if ($user->admin) {
            $this->flashMessage('Uživatel už má admin oprávnění.', 'info');
            $this->redirect('this');
        }

        $this->userRepository->save(new User(
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            id: $user->id,
            role: $user->role,
            faculty: $user->faculty,
            active: $user->active,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: true,
        ));

        $this->flashMessage('Admin oprávnění bylo přidáno.', 'success');
        $this->redirect('this');
    }

    #[NoReturn]
    public function handleRevokeAdmin(int $id): void
    {
        $this->assertValidAdminActionRequest();

        if ($this->isCurrentUserTarget($id)) {
            $this->flashMessage('Vlastní admin oprávnění zde nelze odebrat.', 'warning');
            $this->redirect('this');
        }

        $user = $this->userRepository->findById($id);
        if ($user === null) {
            $this->flashMessage('Uživatel nebyl nalezen.', 'error');
            $this->redirect('this');
        }

        if (!$user->admin) {
            $this->flashMessage('Uživatel nemá admin oprávnění.', 'info');
            $this->redirect('this');
        }

        $this->userRepository->save(new User(
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            id: $user->id,
            role: $user->role,
            faculty: $user->faculty,
            active: $user->active,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: false,
        ));

        $this->flashMessage('Admin oprávnění bylo odebráno.', 'success');
        $this->redirect('this');
    }

    private function isCurrentUserTarget(int $id): bool
    {
        $currentUserId = $this->getUser()->getId();

        return $currentUserId !== null && (int) $currentUserId === $id;
    }

    private function getAdminActionToken(): string
    {
        $section = $this->getSession('adminUsers');
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
