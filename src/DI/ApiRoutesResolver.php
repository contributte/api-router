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

	/**
	 * Place REST API routes at the beginnig of all routes
	 * @param  Nette\Application\IRouter $router
	 * @param  array                     $routes
	 * @return void
	 */
	public function prepandRoutes(Nette\Application\IRouter $router, array $routes)
	{
		$user_routes = [];
		$keys = [];

		foreach ($router as $key => $route) {
			$user_routes[] = $route;
			$keys[] = $key;
		}

		foreach (array_reverse($keys) as $key) {
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
