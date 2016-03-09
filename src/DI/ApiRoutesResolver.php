<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter\DI;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\IRouter;
use Ublaboo\ApiRouter\Exception\ApiRouteWrongRouterException;

class ApiRoutesResolver extends Nette\Object
{

	/**
	 * Place REST API routes at the beginnig of all routes
	 * @param  IRouter $router
	 * @param  array   $routes
	 * @return void
	 */
	public function prepandRoutes(IRouter $router, array $routes)
	{
		if (empty($routes)) {
			return;
		}

		if (!($router instanceof \Traversable) || !($router instanceof \ArrayAccess)) {
			throw new ApiRouteWrongRouterException(sprintf(
				'ApiRoutesResolver can not add ApiRoutes to your router. Use for example %s instead',
				RouteList::class
			));
		}

		$user_routes = $this->findAndDestroyUserRoutes($router);

		/**
		 * Add ApiRoutes first
		 */
		foreach ($routes as $route) {
			$router[] = $route;
		}

		/**
		 * User routes on second place
		 */
		foreach ($user_routes as $route) {
			$router[] = $route;
		}
	}


	/**
	 * @param  IRouter $router
	 * @return array
	 */
	public function findAndDestroyUserRoutes(IRouter $router)
	{
		$keys = [];
		$return = [];

		foreach ($router as $key => $route) {
			$return[] = $route;
			$keys[] = $key;
		}

		foreach (array_reverse($keys) as $key) {
			unset($router[$key]);
		}

		return $return;
	}

}
