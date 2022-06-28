<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;
use App\Model\PartnerManager;
use App\Forms\FormFactory;
//use App\Model;


/**
 * Základní presenter pro všechny ostatní presentery aplikace.
 * @package App\Presenters
 */
abstract class BasePresenter extends Presenter
{

    use Nette\SmartObject;

    /** @var null|string Adresa presenteru pro logování uživatele. */
    # protected $loginPresenter = null;
    protected $loginPresenter = 'Sign:in';

// takto definovana promenna se predava pres HTTP GET - coz je sice funknci,
// ale neni to moc pekne, proto to zrejme nepouziju
//    /**
//     * aktualne vybrany partner uzivatele, pro ktereho se tvori OBJ
//     * @persistent
//     */
//    public $partnerID;

    /** 
     * aktuálně vybraný partner uživatele, pro kterého se tvoří OBJ
     * @var partnerID 
    */
    protected $partnerID;
    
    /** @var partnerAktD záznam načtený z DB dle $partnerID */
    protected $partnerAktD;    

    /** @var FormFactory */
    protected $factory;

    /** @var PartnerManager */
    protected $partnerManager;
   
    /**
     * metody inject*() se volaji automaticky
     * Nette DI takto pojmenované metody v presenterech automaticky volá hned 
     * po vytvoření instance a předá jim všechny požadované závislosti.
     * 
     * V presenterech je preferovaný způsob předávání závislostí pomocí 
     * konstruktoru. Pokud však presenter dědí od společného předka 
     * (např. BasePresenter), je lepší v tomto předkovi použít metody inject*(). 
     * Jejich použitím si totiž ponecháme konstruktor volný pro potomky.
     */
    
    /** 
     * @param FormFactory $factory
     * @param PartnerManager $partnerManager
     */
    public function injectModels(
            FormFactory $factory, 
            PartnerManager $partnerManager
            )
    {
        $this->factory = $factory;
        $this->partnerManager = $partnerManager;            
    }
    
//    /**
//     * Konstruktor s injektovaným modelem pro práci s uzivateli.
//     * @param FormFactory $factory, PartnerManager $partnerManager automaticky injektované třídy
//     */    
//    public function __construct(FormFactory $factory, PartnerManager $partnerManager)
//    {
//        parent::__construct();
//        $this->factory = $factory;
//        $this->partnerManager = $partnerManager;            
//    }

    /**
     * Volá se na začátku každé akce a kontroluje uživatelská oprávnění k této akci.
     * @throws BadRequestException Jestliže je uživatel přihlášen, ale nemá oprávnění k této akci.
     */
    protected function startup()
    {
            parent::startup();
            if (!$this->getUser()->isAllowed($this->getName(), $this->getAction())) {
                    $this->flashMessage('Nejste přihlášen nebo nemáte dostatečná oprávnění.','w3-red');
                    if ($this->loginPresenter) $this->redirect($this->loginPresenter);
            }
            
            // Kontrola ci naplneni $patnerID - jestli ma aktualni user povoleneho 
            // vybraneho partnera. Pokud ne, vraci NULL. 
            // Pokud je NULL, pak se zjistuje, jestli ma uzivatel prideleneho
            // pouze jednoho partnera a kdyz ano, tak ho ihned vybere.
                      
            $sessionSection = $this->getSession()->getSection('base');
            
            if ($this->getUser()->isLoggedIn())  {
                
                if ($sessionSection->get('partnerID')) {
                    $this->partnerID = $sessionSection->get('partnerID');
                } else {
                    $this->partnerID = null;                
                }
                
                if ($this->partnerID) {
                    // nejaky partner jiz byl vybran, zkusim ho nacist z DB,
                    // a to vcetne kontroly, jestli patri uzivateli
                    $this->partnerAktD = $this->partnerManager->getUserPartner(
                            $this->getUser()->getId(),$this->partnerID 
                    );
                    if (!$this->partnerAktD) {
                        $this->flashMessage('Nemáte oprávnění k vybranému partnerovi.','w3-red');
                        $this->partnerID = null;
                    }
                }                
                if (!$this->partnerID) {
                    // jeste nebyl zadny partner vybran, pripadne predchozim 
                    // krokem smazan (napr. byl uzivateli odebran) - zkontroluju, 
                    // jestli nahodou nema jednoho - vychoziho. Pokud ano,
                    // nastavim ho jako aktualniho
                    $partners = $this->partnerManager->getPartners(
                            $this->getUser()->getId()
                    );
                    if ($partners && ($partners->getRowCount() == 1)) {
                        $this->partnerAktD = $partners->fetch();
                        $this->partnerID = $this->partnerAktD['id'];
                    } else {
                        $this->partnerID = null;
                        $this->partnerAktD = null;                        
                    }
                } 
            } else {
                $this->partnerID = null;
                $this->partnerAktD = null;
            }
            
            if ($this->partnerID) {
                $sessionSection->set('partnerID',$this->partnerID);                
            } else {
                $sessionSection->remove('partnerID');
            }
            
    }
        
    /** Volá se před vykreslením každého presenteru a předává společné proměnné do celkového layoutu webu. */
    protected function beforeRender()
    {
            parent::beforeRender();
            $this->template->admin = $this->getUser()->isInRole('admin');
            $this->template->partnerID = $this->partnerID;
            $this->template->partnerAkt = $this->partnerAktD;
    }
}