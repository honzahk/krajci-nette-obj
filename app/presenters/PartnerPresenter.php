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
    //protected $checkPartnerIDCounter;

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
     * Obsluha ajax volání - přidá/odebere položku do/z košíku
     * Používá se pouze ve verzi renderItemListTest(), v ostré verzi nikoliv
     * @param int $zbozi_id ID zboží
     * @param int $pocet počet +/-
     */
    public function handleUpdatePocet($zbozi_id,$pocet)
    {
        $this->checkPartnerID();
        
        if ((!Validators::isNumeric($zbozi_id)) || (!Validators::isNumeric($pocet))) {
            $this->flashMessage('Neočekávaný typ (int).','w3-red');
            $this->redirect('itemList');
        }
        if (!$this->cartManager->addItem($this->getUser()->getId(),$this->partnerID,$zbozi_id,$pocet)) {
            $this->flashMessage('Položku se nepodařilo upravit!','w3-red');
            $this->redirect('Partner:default');
        }

        if ($this->isAjax()) {
            $items = $this->partnerManager->getUserPartnerZboziID(
                    $this->getUser()->getId(),$this->partnerID, $zbozi_id
                    )->fetchAll();
        } else {
            $items = $this->partnerManager->getUserPartnerZbozi(
                    $this->getUser()->getId(),$this->partnerID
                    )->fetchAll();
        }
        if ($items) {
            $this->template->items = $items;
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
     * Přidá/odebere položku do/z košíku
     * Celkový počet, jestli je v násobcích min_obj, bude kontrolován až před 
     * překlopením košíku do objednávky.
     * Nepoužívá se, pouze pro první testy..
     * @param int $zbozi_id ID zboží
     * @param int $pocet počet +/-
     */
    public function actionUpravPocet($zbozi_id,$pocet)
    {    
        $this->checkPartnerID();
        
        //$this->flashMessage($partner_id,'w3-red');
        if ((!Validators::isNumeric($zbozi_id)) || (!Validators::isNumeric($pocet))) {
            $this->flashMessage('Neočekávaný typ (int).','w3-red');
            $this->redirect('itemList');
        }
        if (!$this->cartManager->addItem($this->getUser()->getId(),$this->partnerID,$zbozi_id,$pocet)) {
            $this->flashMessage('Položku se nepodařilo upravit!','w3-red');
        }
        $this->redirect('itemList');
    }
    
    /**
     * Smaže aktuální košík a přidá do něho položky z poslední objednávky.
     */
    public function actionCopyLastOrder()
    {    
        $this->checkPartnerID();
        
        $obj = $this->partnerManager->getLastOrderID($this->partnerID);
        if (!$obj) {
            $this->flashMessage('Nebyla nalezena žádná objednávka v historii!','w3-red');
        } else {

            $cartID = $this->cartManager->getCartIDByUP(
                    $this->getUser()->getId(),$this->partnerID
            );
            if ($cartID) {
                $cart = $this->cartManager->removeCartItems($cartID);
            }

            $rc = $this->cartManager->addItemsFromOrder(
                $this->getUser()->getId(),$this->partnerID,$obj['id']);
            if (!$rc) {
                $this->flashMessage('Nebyly přidány žádné položky!','w3-red');
            } else {
                $this->flashMessage("Byla vytvořena kopie objednávky.",'w3-blue');                
            }            
        }
        
        $this->redirect('Cart:default');
    }

    /**
     * Přidá do aktuálního košíku zboží z poslední objednávky - pokud zboží 
     * v košíku ještě není, pak přidá, jinak přičte počty.
     */
    public function actionAddItemsFromLastOrder()
    {    
        $this->checkPartnerID();
        
        $obj = $this->partnerManager->getLastOrderID($this->partnerID);
        if (!$obj) {
            $this->flashMessage('Nebyla nalezena žádná objednávka v historii!','w3-red');
        } else {
            $rc = $this->cartManager->addItemsFromOrder(
                $this->getUser()->getId(),$this->partnerID,$obj['id']);
            if (!$rc) {
                $this->flashMessage('Nebyly přidány žádné položky!','w3-red');
            } else {
                $this->flashMessage("Byly přidány položky z objednávky.",'w3-blue');                
            }            
        }
        
        $this->redirect('Cart:default');
    }
    
    /**
     * Přidá do aktuálního košíku zboží z poslední objednávky - ale pouze 
     * položky, které jsou v oblíbených. Pokud zboží v košíku ještě není, 
     * pak přidá, jinak přičte počty.
     */
    public function actionAddFavItemsFromLastOrder()
    {    
        $this->checkPartnerID();
        
        $obj = $this->partnerManager->getLastOrderID($this->partnerID);
        if (!$obj) {
            $this->flashMessage('Nebyla nalezena žádná objednávka v historii!','w3-red');
        } else {
            $rc = $this->cartManager->addFavItemsFromOrder(
                $this->getUser()->getId(),$this->partnerID,$obj['id']);
            if (!$rc) {
                $this->flashMessage('Nebyly přidány žádné položky!','w3-red');
            } else {
                $this->flashMessage("Byly přidány položky z objednávky.",'w3-blue');                
            }            
        } 
        
        //$this->redirect('itemListFav');
        $this->redirect('Cart:default');        
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
        // POZOR - nefunguje, getUserPartnerZboziID vraci resultset, musi se 
        // pouzit fetch()!!!
        //if (!$item) {
        //    $this->redirect('itemList');            
        //}        
        $itemArr = $item->fetch();
        if (!$itemArr) {
            $this->redirect('itemList');            
        }    
        
        $sessionSection = $this->getSession()->getSection('base');
        if ($sessionSection->get('oblibene')) {
            $this->template->back_url = 'itemListFav';
        } else {
            $this->template->back_url = 'itemList';
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
     * Zobrazí základní seznam zboží partnera dle kategorií pro jeho
     * výběr/odebrání do/z oblíbených položek - to je pak řešeno ajaxem.
     */
    public function renderItemListFavEdit()
    {                
        // pri ajaxovem volani jiz kontrola probehla
        if (!$this->isAjax()) {
            $this->checkPartnerID();
        }
        
        if (!isset($this->template->items)) {            
            $items = $this->partnerManager->getPartnerZboziFav($this->partnerID)->fetchAll();
            if ($items) {
                $this->template->items = $items; 
            }        
        }                       
    }    

    /**
     * Zobrazí základní seznam zboží partnera dle kategorií.
     * Každé zboží je pak proklikem na editaci tohoto konkrétního zboží.
     * Pokud je zadána kategorie, rozbalí se, jinak se rozbalí první kategorie
     * (to je řešeno v latte - first)
     * Pokud je zadáno zboží a je v rozbalené kategorii, měla by se stránka 
     * odrolovat na řádek se zbožím
     * @param int $kategorie_id
     * @param int $zbozi_id
     */
    public function renderItemList($kategorie_id, $zbozi_id)
    {                
        // pri ajaxovem volani jiz kontrola probehla
        if (!$this->isAjax()) {
            $this->checkPartnerID();
        }
        
        $sessionSection = $this->getSession()->getSection('base');
        if ($sessionSection->get('oblibene')) {
            $sessionSection->remove('oblibene');
        }
        
        if (!isset($this->template->items)) {
            
            $items = $this->partnerManager->getUserPartnerZbozi(
                    $this->getUser()->getId(),$this->partnerID
                    )->fetchAll();
            if ($items) {
                // POZOR, pokud bych vyse volal prirazeni bez fetchAll();,
                // pak by doslo ke dvema problemum:
                // 1. spatne by se vyhodnotila podminka if ($items) { - vzdy
                //    by byla true
                // 2. prirazeni do sablony by pak neumoznilo dvojity pruchod 
                //    pres foreach v sablone !!!! Abych ho mohl pouzit, musim 
                //    prevest ResultSet na Array (fetchAll). Toto se da resit 
                //    ale napriklad predanim dalsiho datoveho zdroje jen 
                //    s kategoriemi
                $this->template->items = $items;
                

                //************************* honza - vypsani item listu pomoci tabu; pouziti jine struktury zdrojovych dat *****************************/
                $isUseTabs = false;
                if($isUseTabs){
                    $itemsStructured = [];
                    foreach($items as $item){
                        if(array_key_exists($item["kategorie_id"],$itemsStructured)==false){
                            $itemsStructured[$item["kategorie_id"]] = [
                                "kategorie_id" => $item["kategorie_id"],
                                "kategorie_nazev" => $item["kategorie_nazev"],
                                "items" => []
                            ];
                        }
                        $itemsStructured[$item["kategorie_id"]]["items"][] = $item;
                    }

                    //vzdy nastav prvni kategorii jako aktivni
                    $this->template->kat_aktivni = 1;

                    //pro ukazku doscrolluj na radek zbozi s timto id
                    $this->template->scrollToZboziId = 208;

                    //tato sablona pouziva jinou strukturu zdrojovych dat
                    $this->template->itemsStructured = $itemsStructured;
                    //nastav adekvatni sablonu
                    $this->template->setFile(dirname(__FILE__) . '/../templates/Partner/itemListTabs.latte');
                }
                //*************************************************************************************************************************************/
            }        
        }
        
        if ($kategorie_id) {
            $this->template->kat_aktivni = $kategorie_id;
        }
        $this->template->scrollToZboziId = $zbozi_id;        
        
        $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
        if ($zavoz) {
            $this->template->zavoz = $zavoz;
        }
        
        //$this->template->checkPartnerIDCounter = $this->checkPartnerIDCounter;
        //$this->redrawControl('counterContainer');
    }    
    
    /**
     * Zobrazí seznam jen oblíbených položek zboží partnera dle kategorií.
     * Každé zboží je pak proklikem na editaci tohoto konkrétního zboží.
     * Pokud je zadána kategorie, rozbalí se, jinak se rozbalí první kategorie
     * (to je řešeno v latte - first) - pokud tedy nakonec nebudou kategorie
     * v tomto zoobrazení zrušeny.
     * Pokud je zadáno zboží a je v rozbalené kategorii, měla by se stránka 
     * odrolovat na řádek se zbožím
     * @param int $kategorie_id
     * @param int $zbozi_id
     */
    public function renderItemListFav($kategorie_id, $zbozi_id)
    {                
        // pri ajaxovem volani jiz kontrola probehla
        if (!$this->isAjax()) {
            $this->checkPartnerID();
        }

        $sessionSection = $this->getSession()->getSection('base');
        if (!$sessionSection->get('oblibene')) {
            $sessionSection->set('oblibene',true);
        }
        
        if (!isset($this->template->items)) {
                       
            $items = $this->partnerManager->getUserPartnerZboziOblibene(
                    $this->getUser()->getId(),$this->partnerID
                    )->fetchAll();
            if ($items) {
                $this->template->items = $items; 
            } else {
                // jeste zadne oblibene polozky neexistuji
                $this->flashMessage('Oblíbené položky musíte nejprve definovat','w3-red');
                $this->redirect('itemListFavEdit');                
            }
        }
        
        if ($kategorie_id) {
            $this->template->kat_aktivni = $kategorie_id;
        }
        
        $this->template->scrollToZboziId = $zbozi_id;                
        
        $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
        if ($zavoz) {
            $this->template->zavoz = $zavoz;
        }
        
    }    
    
    /**
     * Původní zobrazení seznamu zboží - úprava počtů jen ajaxem, bez možnosti 
     * zadání počtu
     */
    public function renderItemListTest()
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
        
        $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
        if ($zavoz) {
            $this->template->zavoz = $zavoz;
        }
        
        //$this->template->checkPartnerIDCounter = $this->checkPartnerIDCounter;
        //$this->redrawControl('counterContainer');
        
        $this->template->setFile(dirname(__FILE__) . '/../templates/Partner/itemList_div_all.latte');
    }    

    /**
     * Testovací funkce na testování různých druhů zobrazení seznamu kategorií
     * se zbožím
     */
    public function renderItemListBSTest()
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

        //$form->addHidden('zbozi_id', 127);
        //$form->addHidden('nazev', 'Cyrilovy brambůrky hořčicové 100g');
        //$form->addHidden('baleni', 20); 
        $form->addHidden('zbozi_id');
        $form->addHidden('baleni'); 
        $form->addHidden('kategorie_id');
        $form->addInteger('pocet_bal', 'Balení:')
                //->setDefaultValue(0)
                //->setHtmlAttribute('id', $id)
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
        
        Debugger::barDump($data);
        //$form->addError('obecná chyba...');
        //$form['pocet_bal']->addError('chyba balení...');
        
        $zbozi_id = $data['zbozi_id'];
        $pocet = $data['baleni']*$data['pocet_bal'];      
        if (isset($data['pulka'])) {
            if ($data['pulka']) {
                $pocet += (int)($data['baleni']/2);
            }
        }
        
        $sessionSection = $this->getSession()->getSection('base');
        if ($sessionSection->get('oblibene')) {
            $listParam = 'itemListFav';
        } else {
            $listParam = 'itemList';
        }
                    
        if (!$this->cartManager->setItem($this->getUser()->getId(),$this->partnerID,$zbozi_id,$pocet)) {
            $this->flashMessage('Položku se nepodařilo upravit!','w3-red');
            //$this->redirect('Partner:itemList');
            $this->redirect('Partner:'.$listParam);
        }
        if (isset($data['kategorie_id'])) {
            //$this->redirect('Partner:itemList',$data['kategorie_id']);
            $this->redirect('Partner:'.$listParam,$data['kategorie_id'],$zbozi_id);
        } else {
            //$this->redirect('Partner:itemList');
            $this->redirect('Partner:'.$listParam,0,$zbozi_id);
        }
    }

    /**
     * Obsluha ajax volání - přidá/odebere položku do/z oblíbených
     * @param int $zbozi_id ID zboží
     * @param string $akce add/del
     */
    public function handleUpdateFav($zbozi_id,$akce)
    {
        $this->checkPartnerID();
        
        if (!Validators::isNumeric($zbozi_id)) {
            $this->flashMessage('Neočekávaný typ (int).','w3-red');
            $this->redirect('itemListFavEdit');
        }
        
        // samotna akce pridani/odebrani
        if ($akce == 'add') {
            // pridame
            $this->partnerManager->addFavItem($this->partnerID, $zbozi_id);
        } else {
            // odebereme
            $this->partnerManager->deleteFavItem($this->partnerID, $zbozi_id);            
        }

        if ($this->isAjax()) {
            $items = $this->partnerManager->getPartnerZboziFavID(
                    $this->partnerID, $zbozi_id
                    )->fetchAll();
        } else {
            $items = $this->partnerManager->getPartnerZboziFav(
                    $this->partnerID
                    )->fetchAll();
        }
        if ($items) {
            $this->template->items = $items;
        }
        $this->redrawControl('itemsContainer');
        
    }
    
    /**
     * Zobrazí poslední objednávku
     */
    public function renderLastOrder()
    {    
        $this->checkPartnerID();
        
        $obj = $this->partnerManager->getLastOrderID($this->partnerID);
        if (!$obj) {
            $this->flashMessage('Nebyla nalezena žádná objednávka v historii!','w3-red');
            $this->redirect('itemList');
        }
        
        $items = $this->partnerManager->getUserPartnerZboziObj(
                    $this->getUser()->getId(), $this->partnerID, $obj['id']
                )->fetchAll();
        if (!$items) {
            // nebylo načtené žádné zboží, toto by nemělo nastat, jedině by
            // třeba došlo ke smazání zboží z tabulky zbozi
            $this->flashMessage('Nebylo nalezeno žádné zboží v historii!','w3-red');
            $this->redirect('itemList');            
        }
        $this->template->obj = $obj;
        $this->template->items = $items;
                        
    }
    
//    protected function startup() {
//        parent::startup();
//        $this->checkPartnerIDCounter = 0;
//    }

}
