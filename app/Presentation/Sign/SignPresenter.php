<?php
declare(strict_types=1);

namespace App\Presentation\Sign;

use App\Presentation\BasePresenter;
use JetBrains\PhpStorm\NoReturn;

final class SignPresenter extends BasePresenter
{

    #[NoReturn]
    public function actionIn(): void
    {
        $this->redirect('Home:');
    }


    #[NoReturn]
    public function actionUp(): void
    {
        $this->redirect('Home:');
    }


    #[NoReturn]
    public function actionOut(): void
    {
        $this->getUser()->logout();
        $this->flashMessage('Byl jste odhlášen.', 'info');
        $this->redirect('Home:');
    }
}