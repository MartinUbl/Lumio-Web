<?php
declare(strict_types=1);

namespace App\Presentation\Forgot;

use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;

final class ForgotPresenter extends BasePresenter
{
    protected function createComponentForgotForm(): Form
    {
        $form = new Form();
        $form->addEmail('email', 'Tvůj e-mail')
            ->setRequired('Vyplň svůj e-mail.');
        $form->addSubmit('send', 'Odeslat');
        $form->onSuccess[] = [$this, 'forgotFormSucceeded'];

        return $form;
    }

    public function forgotFormSucceeded(Form $form, array $values): void
    {
        $email = $values['email'];
        if ($this->userRepository->forgotPassword($email)) {
            $this->flashMessage('Obnova hesla byla poslána na e-mail.', 'info');
            $this->redirect('Home:');
        } else {
            $this->flashMessage('Při pokusu o obnovu hesla nastala chyba.', 'error');
        }
    }
}