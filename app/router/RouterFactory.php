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
                $router->addRoute('o-projektu/', 'Homepage:about');
                $router->addRoute('kontakt/', 'Homepage:contact');
                $router->addRoute('kosik/', 'Cart:default');
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
                $router->addRoute('pobocka/[<action>]', array(
                        'presenter' => 'Partner',
                        'action' => array(
                                // řetězec v URL => akce presenteru
                                Route::VALUE => 'default',
                                Route::FILTER_TABLE => array(
                                        'detail' => 'show',
                                        'vyber' => 'vyberPartnera',
                                        'zbozi' => 'itemList',
                                        'zbozi-edit' => 'itemCalc'
                                ),
                                Route::FILTER_STRICT => true
                        )
                ));
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
		$router->addRoute('<presenter>/<action>', 'Homepage:default');
		return $router;
	}
}
