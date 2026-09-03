<?php
declare(strict_types=1);

namespace App\Presentation;

abstract class BaseAdminPresenter extends BasePresenter
{
    protected function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro přístup do administrace se musíte přihlásit.', 'warning');
            $this->redirect('Sign:in');
        }

        if (!$this->isCurrentUserAdmin()) {
            $this->flashMessage('Nemáte oprávnění pro vstup do administrace.', 'danger');
            $this->redirect('Home:');
        }
    }
}
