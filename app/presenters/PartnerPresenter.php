<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Utils\Validators;
use App\Model\CartManager;
//use Nette\Utils\DateTime;
//use App\Model\UserManager;
//use App\Model\PartnerManager;
//use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class PartnerPresenter extends BasePresenter
{

    /** @var Model\CartManager */
    protected $cartManager;
    
    /**
     * 
     * @param Model\CartManager $cartManager
     */
    public function injectPartnerModels(CartManager $cartManager)
    {
        $this->cartManager = $cartManager;
    }
    
    /**
     * Kontrola, jestli je vybrán partner, v opačném případě přesměruje
     * na výběr partnerů
     */
    private function checkPartnerID()
    {
        //$this->checkPartnerIDCounter++;
                
        if (!$this->partnerID) {
            // kontrola pro jistotu pri podvrzeni dat, normalne by nemelo
            // nikdy nastat
            $this->flashMessage('Není vybrán žádný partner!','w3-red');
            $this->redirect('Partner:default');
        }        
    }
    
    /**
     * Obsluha ajax volání - přidá/odebere 1/2 balení položky do/z košíku
     * Připraveno pro renderItemCalc(), ale nakonec zřejmě nepoužijeme
     * @param int $zbozi_id ID zboží
     * @param int $pocet počet +/-
     */
    public function handleUpdatePulka($zbozi_id,$pocet)
    {
        $this->checkPartnerID();
        
        if ((!Validators::isNumeric($zbozi_id)) || (!Validators::isNumeric($pocet))) {
            $this->flashMessage('Neočekávaný typ (int).','w3-red');
            $this->redirect('itemList');
        }
        if (!$this->cartManager->addItem($this->getUser()->getId(),$this->partnerID,$zbozi_id,$pocet)) {
            $this->flashMessage('Položku se nepodařilo upravit!','w3-red');
            $this->redirect('itemList');
        }
        
        $this->redrawControl('itemsContainer');
        
    }
       
    /**
     * Načte seznam všech partnerů uživatele. Admin se zatím nerozlišuje - vrací
     * stejnou množinu. Pokud bych umožnil někomu opravdu hodně partnerů, pak
     * by bylo třeba zřejmě stránkovat. Ve výpisu budou odkazy, kterými se partner
     * vybere jako aktivní.
     */
    public function renderDefault()
    {            
        $this->template->partners = $this->partnerManager->getPartners(
                $this->getUser()->getId()
        );
    }

    /**
     * Vybere uživatele jako aktuálního, případně se při null pokusí najít výchozího.
     * Ten se vybere automaticky, pokud existuje pouze jeden
     * @param int $partner_id ID partnera
     */
    public function actionVyberPartnera($partner_id)
    {    
        //$this->flashMessage($partner_id,'w3-red');
        if (!$partner_id) {
            // pokusime se najit vychoziho partnera
            $partners = $this->partnerManager->getPartners($this->getUser()->getId());
            if ($partners->getRowCount() == 1) {
                // existuje pouze jeden partner, vybereme ho
                $partner_id = $partners->fetch()['id'];
            } else {
                $this->redirect('default');                
            }                
        } elseif (!Validators::isNumeric($partner_id)) {
            $this->flashMessage('Neočekávaný typ (int).','w3-red');
            $this->redirect('default');
        }
        
        // zkusim nacist partnera z DB, a to vcetne kontroly, jestli patri uzivateli
        $partner = $this->partnerManager->getUserPartner(
                $this->getUser()->getId(),$partner_id
        );
        if (!$partner) {
            $this->flashMessage('Nemáte oprávnění k vybranému partnerovi.','w3-red');
            $this->partnerID = null;
            $this->redirect('Homepage:default');
        }
        $sessionSection = $this->getSession()->getSection('base');
        $sessionSection->set('partnerID',$partner_id);
        
        // overim existenci kosiku
        $cartID = $this->cartManager->getCartIDByUP(
                $this->getUser()->getId(),$partner_id
        );
        if ($cartID) {
            // kontrola, jestli je v kosiku vubec neco platneho
            $cart = $this->cartManager->getAllItems($cartID);
            if ($cart->getRowCount() == 0) {            
                // Košík je prázdný, presmeruji na vyber zbozi partnera
                $this->redirect('Partner:itemList');
            }
            // kosik je neprazdny, zobrazime ho
            $this->redirect('Cart:default');
        } else {
            $this->flashMessage('Nepodařilo se vytvořit košík.','w3-red');            
            $this->redirect('Homepage:default');
        }            
        
    }
    
    /**
     * Zobrazení editoru jedné položky košíku. Může být voláno i po obsluze
     * ajaxem.
     * @param int $zbozi_id ID zboží
     */
    public function renderItemCalc($zbozi_id)
    {    
        // pri ajaxovem volani jiz kontrola probehla
        if (!$this->isAjax()) {
            $this->checkPartnerID();
        }
        
        $item = $this->partnerManager->getUserPartnerZboziID(
                $this->getUser()->getId(),$this->partnerID, $zbozi_id
                );        
        $itemArr = $item->fetch();
        if (!$itemArr) {
            $this->redirect('itemList');            
        }    
        
        $this->template->item = $itemArr;
        
        $this['itemEditorForm']->setDefaults([
            'zbozi_id' => $itemArr['zbozi_id'],
            'baleni' => $itemArr['baleni'],
            'kategorie_id' => $itemArr['kategorie_id'],
            'pocet_bal' => ''
                ]);
    }
    
    /** Načte a zobrazí info o partnerovi do šablony podle jeho ID.
     * @param string $ID ID partnera
     */
    public function renderShow($ID)
    {
        if ($this->getUser()->isInRole('admin')) {
            $partner = $this->partnerManager->getPartner($ID);            
        } else {
            $partner = $this->partnerManager->getUserPartner(
                    $this->getUser()->getId(),$ID
                    );
        }
        if ($partner) {
            $this->template->partner = $partner;
        } else {
            $this->flashMessage('Partner nebyl nalezen nebo k němu nemáte přístup.','w3-red');
            $this->redirect('default');
        }
    }

    /**
     * Zobrazí základní seznam zboží partnera dle kategorií.
     * Každé zboží je pak proklikem na editaci tohoto konkrétního zboží.
     * Pokud je zadána kategorie, rozbalí se, jinak se rozbalí první kategorie
     * (to je řešeno v latte - first)
     * @param int $kategorie_id
     */
    public function renderItemList($kategorie_id)
    {                
        // pri ajaxovem volani jiz kontrola probehla
        if (!$this->isAjax()) {
            $this->checkPartnerID();
        }
        
        if (!isset($this->template->items)) {
                       
            $items = $this->partnerManager->getUserPartnerZbozi(
                    $this->getUser()->getId(),$this->partnerID
                    )->fetchAll();
            if ($items) {
                $this->template->items = $items; 
            }        
        }
        
        if ($kategorie_id) {
            $this->template->kat_aktivni = $kategorie_id;
        }
        
        $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
        if ($zavoz) {
            $this->template->zavoz = $zavoz;
        }
        
    }    

    /**
     * Vrátí formulář pro registraci uživatele.
     * @return Form formulář pro editaci uživatele.
     */
    protected function createComponentItemEditorForm()
    {
        $form = $this->factory->create();
        $this->factory->makeBootstrapCervenan($form);        
        //$this->factory->makeBootstrap3($form);   
        //$this->factory->makeBootstrap3Cervenan($form);        
        
        $form->addProtection(); // Cross-Site Request Forgery (CSRF) attack protection

        $form->addHidden('zbozi_id');
        $form->addHidden('baleni'); 
        $form->addHidden('kategorie_id');
        $form->addInteger('pocet_bal', 'Balení:')
                ->setHtmlAttribute('class', 'form-control input-number')
                ->setRequired('Zadejte prosím počet') // text se ignoruje
                ->addRule($form::MIN, 'Počet nesmí být záporný.', 0); // text se ignoruje
        $form->addCheckbox('pulka', '+ půl balení')
                ->setDefaultValue(false);                
        $form->addSubmit('send', 'Nastavit');
        
        $form->onSuccess[] = [$this, 'itemEditorFormSucceeded'];
        
        return $form;
    }
    
    public function itemEditorFormSucceeded($form, $data)
    {
        $this->checkPartnerID();
              
        $zbozi_id = $data['zbozi_id'];
        $pocet = $data['baleni']*$data['pocet_bal'];      
        if (isset($data['pulka'])) {
            if ($data['pulka']) {
                $pocet += (int)($data['baleni']/2);
            }
        }
        
        if (!$this->cartManager->setItem($this->getUser()->getId(),$this->partnerID,$zbozi_id,$pocet)) {
            $this->flashMessage('Položku se nepodařilo upravit!','w3-red');
            $this->redirect('Partner:itemList');
        }
        if (isset($data['kategorie_id'])) {
            $this->redirect('Partner:itemList',$data['kategorie_id']);
        } else {
            $this->redirect('Partner:itemList');
        }
    }
}
