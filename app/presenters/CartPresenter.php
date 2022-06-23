<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use App\Model\CartManager;
use Nette\Utils\DateTime;

class CartPresenter extends BasePresenter
{
    const ZPRAVA_MAX_LENGTH = 1024;
    
    /** @var Model\CartManager */
    protected $cartManager;
    
    /**
     * 
     * @param Model\CartManager $cartManager
     */
    public function injectCartModels(CartManager $cartManager)
    {
        $this->cartManager = $cartManager;
    }

    public function renderDefault()
    {        
        if ($this->partnerID) {
            $cartID = $this->cartManager->getCartIDByUP(
                    $this->getUser()->getId(),$this->partnerID
            );
            if ($cartID) {
                // kontrola, jestli v kosiku vubec je neco platneho
                $cart = $this->cartManager->getAllItems($cartID);
//                if ($cart->getRowCount() == 0) {            
//                    $this->flashMessage('Košík je prázdný!!!','w3-red');
//                }               
                //$this->template->cart = $this->cartManager->getAllItems($cartID);
                $this->template->cart = $cart;
            } else {
                $this->flashMessage('Nepodařilo se vytvořit košík.','w3-red');            
            }            
            
            $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
            if ($zavoz) {
                $this->template->zavoz = $zavoz;
                $sessionSection = $this->getSession()->getSection('zavoz');
                $sessionSection['zavoz'] = $zavoz;
            } else {
                $sessionSection->remove('zavoz');
            }            
            
        } else {
            $this->flashMessage('Není vybrán žádný partner!','w3-red');
            $this->redirect('Partner:default');
        }

    }
    
    /**
     * Vrátí formulář pro překlopení košíku do objednávky.
     * @return Form formulář pro překlopení košíku do objednávky.
     */
    protected function createComponentMakeOrderForm()
    {
        // zatím jen editace zprávy    
        
        $form = $this->factory->create();
        $this->factory->makeBootstrapCervenan($form);        
        //$this->factory->makeBootstrap3($form);   
        //$this->factory->makeBootstrap3Cervenan($form);        
        
        $form->addProtection(); // Cross-Site Request Forgery (CSRF) attack protection

        $form->addText('zprava', 'Zpráva pro dodavatele:')
             ->setOption('description', sprintf('Maximální počet znaků: %d.', self::ZPRAVA_MAX_LENGTH))
             ->addRule($form::MAX_LENGTH, NULL, self::ZPRAVA_MAX_LENGTH);                
        
        $form->addSubmit('send', 'Odeslat objednávku')
                ->setHtmlAttribute('class','w3-red');
        
        $form->onSuccess[] = [$this, 'saveObjFormSucceeded'];
        return $form;
    }
    
    /**
     * Funkce se vykoná při úspěsném odeslání formuláře; zpracuje hodnoty formuláře.
     * @param Form $form formulář editoru
     * @param ArrayHash $values odeslané hodnoty formuláře
     */
    public function saveObjFormSucceeded($form, $values)
    {
        if ($this->partnerID) {
            $cartID = $this->cartManager->getCartIDByUP(
                    $this->getUser()->getId(),$this->partnerID
            );
            if (!$cartID) {
                $this->flashMessage('Nepodařilo se vytvořit košík!!!','w3-red');
                $this->redirect('Homepage:default');
            }
            
            // kontrola, jestli v kosiku vubec je neco platneho
            $cart = $this->cartManager->getAllItems($cartID);
            if ($cart->getRowCount() == 0) {            
                $this->flashMessage('Košík je prázdný!!!','w3-red');
                $this->redirect('default');
            }
            
            // vytahnu datum zavozu, ktery byl zobrazen v kosiku pred stiskem
            // tlacitka odeslat objednavku
            $sessionSection = $this->getSession()->getSection('zavoz');
            $sessZavoz = $sessionSection['zavoz'];
            
            // spocitam aktualni datum zavozu
            // pokud se lisi od session, upozornim na to !!!
            $zavoz = $this->partnerManager->vratZavoz($this->partnerID);
            if ($zavoz) {
                $this->template->zavoz = $zavoz;
                if ($sessZavoz) {
                    if ($sessZavoz['trasaDate'] && $zavoz['trasaDate'] &&
                            ($sessZavoz['trasaDate']->format('Y-m-d') != 
                             $zavoz['trasaDate']->format('Y-m-d'))) {
                        $sessionSection['zavoz'] = $zavoz;
                        $this->flashMessage('Pozor - došlo ke změně možného data závozu! Pokud chcete objednávku i přesto odeslat, odešlete ji znovu...','w3-red');
                        $form->addError('Pozor - došlo ke změně možného data závozu! Pokud chcete objednávku i přesto odeslat, odešlete ji znovu...');
                        // volani return ponecha otevreny formular a mimo jine 
                        // take ponecha vyplnena data ve formulari !!!
                        return;
                    }                                                        
                }                
            }          
            
            // v pripade, ze kontrola probehla v poradku, smazu tuto informaci
            $sessionSection->remove('zavoz');
            
            // vime, ze v kosiku neco je, muzeme tedy preklopit do OBJ            
            $obj_id = $this->cartManager->makeOrder($cartID,$values["zprava"],$zavoz['trasaDate']);
            if (!$obj_id) {
                $this->flashMessage('Při vytváření objednávky došlo k chybě!!!','w3-red');
                $this->redirect('default');
            }
            $this->cartManager->removeCartItems($cartID);
            $this->flashMessage("Byla vytvořena objednávka (č.$obj_id) !!!",'w3-blue');
            $this->redirect('Homepage:default');
            //$this->redirect('Order:default',$obj_id);

        } else {
            $this->flashMessage('Není vybrán žádný partner!','w3-red');
            $this->redirect('Partner:default');
        }
    }
    
}
