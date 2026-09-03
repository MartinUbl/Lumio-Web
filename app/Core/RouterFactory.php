<?php declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

        $router->addRoute('', 'Home:default');
        $router->addRoute('registrace', 'Sign:up');
        $router->addRoute('prihlaseni', 'Sign:in');
        $router->addRoute('odhlaseni', 'Sign:out');
        $router->addRoute('profil', 'Profile:default');
        $router->addRoute('akce', 'Event:default');
        $router->addRoute('akce/navrhnout', 'Event:suggest');
        $router->addRoute('akce/detail/<id>', 'Event:detail');
        $router->addRoute('reporty', 'Reports:default');
        $router->addRoute('odbornici', 'Expert:default');
        $router->addRoute('reset/<code>', 'Reset:default');
        $router->addRoute('forgot', 'Forgot:default');

        $router->addRoute('admin', 'Dashboard:default');
        $router->addRoute('admin/uzivatele', 'AdminUsers:default');
        $router->addRoute('admin/akce', 'AdminEvents:default');
        $router->addRoute('admin/akce/upravit/<id>', 'AdminEvents:edit');
        $router->addRoute('admin/odbornici', 'AdminExperts:default');
        $router->addRoute('admin/odbornici/novy', 'AdminExperts:edit');
        $router->addRoute('admin/odbornici/upravit/<id>', 'AdminExperts:edit');
        $router->addRoute('admin/tagy', 'AdminTags:default');
        $router->addRoute('admin/reporty', 'AdminReports:default');

		return $router;
	}
}
