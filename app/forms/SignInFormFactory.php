<?php

namespace App\Forms;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\User;

class SignInFormFactory
{
	use Nette\SmartObject;

	/** @var FormFactory */
	private $factory;

	/** @var User */
	private $user;


	public function __construct(FormFactory $factory, User $user)
	{
		$this->factory = $factory;
		$this->user = $user;
	}


	/**
	 * @return Form
	 */
	public function create(callable $onSuccess)
	{
		$form = $this->factory->create();
                $this->factory->makeBootstrapCervenan($form);

                $form->addText('username', 'Uživatelské jméno:')
			->setRequired('Zadejte prosím uživatelské jméno.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte prosím heslo.');

		$form->addCheckbox('remember', ' Zůstat přihlášen.');

		$form->addSubmit('send', 'Přihlásit');

		$form->onSuccess[] = function (Form $form, $values) use ($onSuccess) {
			try {
                            $this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
                            $this->user->login($values->username, $values->password);
			} catch (Nette\Security\AuthenticationException $e) {
                            $form->addError('Špatné uživatelské jméno nebo heslo.');
                            $form['username']->addError('Test username error.');
                            $form['password']->addError('Test password error.');
                            return;
			}
			$onSuccess();
		};

		return $form;
	}

}
