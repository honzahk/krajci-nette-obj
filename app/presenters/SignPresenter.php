<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Forms;


class SignPresenter extends BasePresenter
{
	/** @var Forms\SignInFormFactory @inject */
	public $signInFactory;

	/**
	 * Sign-in form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentSignInForm()
	{
		return $this->signInFactory->create(function () {
                    // $this->redirect(':Member:Memberhome:');
                    $this->redirect('Partner:vyberPartnera');
		});
	}

	public function actionOut()
	{
		$this->getUser()->logout(); // ponechani identity
                //$this->getUser()->logout(true); // smazat i identitu               
        }
}
