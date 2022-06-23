<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;
                //$router->addRoute('ajax/', 'AjaxTest:default');
                $router->addRoute('o-projektu/', 'Homepage:about');
                $router->addRoute('kontakt/', 'Homepage:contact');
                $router->addRoute('kosik/', 'Cart:default');
                //$router->addRoute('uzivatel-hp/', 'Homepage:userInfo');
                //$router->addRoute('uzivatel/', 'User:default');
                $router->addRoute('uzivatel/[<action>]', array(
                        'presenter' => 'User',
                        'action' => array(
                                // řetězec v URL => akce presenteru
                                Route::VALUE => 'default',
                                Route::FILTER_TABLE => array(
                                        'editace' => 'editAccount',
                                        'heslo' => 'editPassword'
                                ),
                                Route::FILTER_STRICT => true
                        )
                ));
                //$router->addRoute('partner/[<action>][/<partner_id>]', array(
                $router->addRoute('pobocka/[<action>]', array(
                        'presenter' => 'Partner',
                        'action' => array(
                                // řetězec v URL => akce presenteru
                                Route::VALUE => 'default',
                                Route::FILTER_TABLE => array(
                                        'detail' => 'show',
                                        'vyber' => 'vyberPartnera',
                                        'zbozi' => 'itemList',
                                        'zbozi-uprava' => 'itemCalc',
                                        'pridej-dle-posledni-obj' => 'addItemsFromLastOrder',
                                        'zbozi-oblibene' => 'itemListFav',
                                        'pridej-oblibene-dle-posledni-obj' => 'addFavItemsFromLastOrder',
                                        'zbozi-oblibene-uprava' => 'itemListFavEdit',
                                        'zbozi-oblibene-uprava-id' => 'updateFav',
                                        'posledni-obj' => 'lastOrder',
                                        'kopie-obj' => 'copyLastOrder'
                                ),
                                Route::FILTER_STRICT => true
                        )
                ));
                //$router->addRoute('test/', 'Homepage:test');
                //$router->addRoute('objednavka/', 'Order:default');
                // URL cokoliv/ 
                $router->addRoute('<action>/', array(
                        'presenter' => 'Sign',
                        'action' => array(
                                // řetězec v URL => akce presenteru
                                // slovník - čeština vs název v kódu
                                Route::FILTER_TABLE => array(
                                        'prihlaseni' => 'in',
                                        'odhlasit' => 'out'
                                ),
                                // odmítnutí čehokoliv, co není ve slovníku
                                Route::FILTER_STRICT => true
                        )
                ));
                // URL cokoliv/cokoliv 
                //$router->addRoute('<presenter>/<action>[/<id>]', 'Homepage:default');
		$router->addRoute('<presenter>/<action>', 'Homepage:default');
		return $router;
	}
}
