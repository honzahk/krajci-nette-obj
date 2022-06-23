<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model\UserManager;
//use App\Model\PartnerManager;
//use App\Forms\FormFactory;
use Nette\Application\UI\Form;

// pokud neni zadano use App\Model;, pak modul nezna DuplicateNameException 
// a DuplicateEmailException a prerusi se registerUser, pripadne updateUser, 
// volane v editorFormSucceeded
// - skonci s chybou, nedojde k osetreni techto chyb pres try/catch
use App\Model; 

class UserPresenter extends BasePresenter
{

    const PASSWORD_MIN_LENGTH = 3; // 7
    
    /** @var UserManager Instance třídy modelu pro práci s uživateli. */
    protected $userManager;

    /** @var userD záznam načtený z DB. */
    protected $userD;
    
//    /**
//     * Konstruktor s injektovaným modelem pro práci s uzivateli.
//     * @param FormFactory $factory, PartnerManager $partnerManager, UserManager $userManager automaticky injektované třídy
//     */    
//    public function __construct(FormFactory $factory, PartnerManager $partnerManager, UserManager $userManager)
//    {
//        parent::__construct($factory,$partnerManager);
//        $this->userManager = $userManager;
//    }

    /**
     * Konstruktor s injektovaným modelem pro práci s uzivateli.
     * @param UserManager $userManager automaticky injektované třídy
     */    
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }
    
    /**
    * Common render method.
    * @return void
    */
    protected function beforeRender()
    {
        parent::beforeRender();
        //$this->template->userDetail = $this->getUser()->getIdentity()->getData();        
        $this->template->userDetail = $this->userD;
    }
    
    /**
     * Vykresluje formulář pro editaci uživatele (bez hesla).
     */
    public function actionEditAccount()
    {        
        // funguje take
        // $this['editorForm']->setDefaults($this->template->userDetail = $this->getUser()->getIdentity()->getData());
        $this['editorForm']->setDefaults($this->userD);
    }
    
    /**
     * Vrátí formulář pro registraci uživatele.
     * @return Form formulář pro editaci uživatele.
     */
    protected function createComponentEditorForm()
    {
        // editace loginu a emailu
        
        //$form = new Form;
        
        $form = $this->factory->create();
        $this->factory->makeBootstrapCervenan($form);        
        //$this->factory->makeBootstrap3($form);   
        //$this->factory->makeBootstrap3Cervenan($form);        
        
        $form->addProtection(); // Cross-Site Request Forgery (CSRF) attack protection

        $form->addText('login', 'Login:')
             ->setRequired('Zadejte prosím login.')
             ->addRule($form::MAX_LENGTH, NULL, 100);

        $form->addEmail('email', 'E-mail:')
             ->setRequired('Zadejte prosím e-mail.')
             ->addRule($form::MAX_LENGTH, NULL, 100);

        $form->addSubmit('send', 'Uložit');
        
        $form->onSuccess[] = [$this, 'editorFormSucceeded'];
        return $form;
    }

    /**
     * Funkce se vykoná při úspěsném odeslání formuláře; zpracuje hodnoty formuláře.
     * @param Form $form formulář editoru
     * @param ArrayHash $values odeslané hodnoty formuláře
     */
    public function editorFormSucceeded($form, $values)
    {
        try {            
            $values["id"] = $this->getUser()->getId();
            // nebo // $values["id"] = $this->userD[id];
            $this->userManager->updateUser($values);
            
            // u prihlaseneho uzivatele je treba znovu nacist zmenene udaje
            // to se ale jiz deje v metode startup(), tedy data jsou jiz obnovena
            // $this->userD = $this->userManager->getUserByID($this->getUser()->getId());
            // ale je to treba pro identitu v aplikaci
            unset($values["id"]); // toto neni treba obnovovat
            // $values = array(‘id’ => 123, ‘name’ => ‘Joe Sixpack’, ‘role’ => ‘administrator’);
            $currentIdentity = $this->getUser()->getIdentity();
            foreach ($values as $attribute => $value) {
                $currentIdentity->$attribute = $value;
            }
            $this->flashMessage('Údaje byly úspěšně uloženy.','w3-blue');            
            $this->redirect('default');
            
        } catch (Model\DuplicateNameException $e) { 
            $form['login']->addError('Uživatelské jméno je již používáno.');
        } catch (Model\DuplicateEmailException $e) {
            $form['email']->addError('Email je již použit u účtu jiného uživatele ('.$e->getMessage().')');
        }
    }
        
    /**
     * Vykresluje formulář pro editaci hesla uživatele.
     */
    public function actionEditPass()
    {
    }
    
    /**
     * Vrátí formulář pro změnu hesla uživatele.
     * @return Form formulář pro editaci hesla uživatele.
     */
    protected function createComponentEditPassForm()
    {
        //$form = new Form;
        
        $form = $this->factory->create();
        $this->factory->makeBootstrapCervenan($form);        
        //$this->factory->makeBootstrap3($form);        
        //$this->factory->makeBootstrap3Cervenan($form);        
        
        $form->addProtection(); // Cross-Site Request Forgery (CSRF) attack protection

        $form->addPassword('passwordCurr', 'Zadejte současné heslo:')
             ->setRequired('Zadejte prosím současné heslo.');
        
        $form->addPassword('password', 'Zadejte nové heslo:')
             ->setOption('description', sprintf('Minimální počet znaků: %d.', self::PASSWORD_MIN_LENGTH))
             ->setRequired('Zadejte prosím nové heslo.')
             ->addRule($form::MIN_LENGTH, NULL, self::PASSWORD_MIN_LENGTH);
        
        $form->addPassword('passwordVerify', 'Opakujte heslo:')
             // ->setRequired('Zadejte prosím heslo znovu.')
             ->addRule(Form::FILLED, 'Zadejte prosím nové heslo znovu.') // to same co setRequired('Zadejte prosím heslo znovu.')
             ->addRule(Form::EQUAL, 'Hesla se neshodují.', $form['password']);        
        $form->addSubmit('send', 'Uložit');
        
        $form->onSuccess[] = [$this, 'editPassFormSucceeded'];
        return $form;
    }

    /**
     * Funkce se vykoná při úspěsném odeslání formuláře; zpracuje hodnoty formuláře.
     * @param Form $form formulář editoru
     * @param ArrayHash $values odeslané hodnoty formuláře
     */
    public function editPassFormSucceeded($form, $values)
    {
        if ($this->userManager->checkPassword($this->userD['login'],$values['passwordCurr'])) {
            $values["id"] = $this->userD['id'];
            // nebo // $values["id"] = $this->getUser()->getId();
            unset($values["passwordCurr"]);
            unset($values["passwordVerify"]);
            $this->userManager->updateUser($values);
            $this->flashMessage('Vaše heslo bylo změněno.','w3-blue');
            $this->redirect('default');
        } else {
            $form->addError('Zadané současné heslo není správné.');
            // zobrazeni varovani primo u polozky
            //$form['passwordCurr']->addError('Zadané heslo není správné.');
        }
    }

    protected function startup()
    {
        // overeni, jestli je uzivatel prihlasen
        parent::startup();

        // ochrana existence uctu - kdyby byl napr. na pozadi jinym procesem smazan
        // zaroven nacte/obnovi data do/v userD
        $this->userD = $this->userManager->getUserByID($this->getUser()->getId());
        //if (!$this->userManager->getUserByID($this->getUser()->getId())) {
        if (!$this->userD) {
            $this->flashMessage('Uživatel nebyl nalezen.','w3-red');
            $this->getUser()->logout();
            $this->redirect(':Homepage:');
        }                
//        if ($this->getUser()->isInRole('admin')) {                
//            $this->flashMessage('Jste přihlášen jako admin, tato sekce je určena pro členy.','w3-blue w3-small');
//        }

    }

}
