<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter\DI;

use Nette;

class ApiRoutesResolver extends Nette\Object
{

	public function prepandRoutes(Nette\Application\IRouter $router, array $routes)
	{
		$user_routes = [];

		foreach ($router as $key => $route) {
			$user_routes[] = $route;
			unset($router[$key]);
		}

		foreach ($routes as $route) {
			$router[] = $route;
		}

		foreach ($user_routes as $route) {
			$router[] = $route;
		}
	}

}
