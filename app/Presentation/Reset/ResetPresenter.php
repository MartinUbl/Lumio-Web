<?php
declare(strict_types=1);

namespace App\Presentation\Reset;

use App\Presentation\BasePresenter;
use Nette\Application\UI\Form;

final class ResetPresenter extends BasePresenter
{
    private string $code = "";

    public function renderDefault(string $code): void
    {
        $this->code = $code;
    }

    protected function createComponentResetForm(): Form
    {
        $form = new Form();
        $form->addPassword('password', 'Nové heslo')
            ->setRequired('Vyplň nové heslo.');
        $form->addSubmit('send', 'Uložit změny');
        $form->onSuccess[] = [$this, 'resetFormSucceeded'];

        return $form;
    }

    public function resetFormSucceeded(Form $form, array $values): void
    {
        $password = $values['password'];
        if ($this->userRepository->resetPassword($this->getParameter('code'), $password)) {
            $this->flashMessage('Heslo bylo úspěšně změněno.', 'success');
            $this->redirect('Home:');
        } else {
            $this->flashMessage('Při pokusu o změnu hesla nastala chyba', 'error');
        }
    }
}