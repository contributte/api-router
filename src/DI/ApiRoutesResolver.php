<?php

declare(strict_types=1);

namespace Contributte\ApiRouter\DI;

use ArrayAccess;
use Contributte\ApiRouter\Exception\ApiRouteWrongRouterException;
use Nette\Application\IRouter;
use Nette\Application\Routers\RouteList;
use Traversable;

class ApiRoutesResolver
{

	/**
	 * Place REST API routes at the beginnig of all routes
	 */
	public function prepandRoutes(IRouter $router, array $routes): void
	{
		if (!$routes) {
			return;
		}

		if (!($router instanceof Traversable) || !($router instanceof ArrayAccess)) {
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


	public function findAndDestroyUserRoutes(IRouter $router): array
	{
		$keys = [];
		$return = [];

		foreach ($router->getRouters() as $key => $route) {
			$return[] = $route;
			$keys[] = $key;
		}

		foreach (array_reverse($keys) as $key) {
			unset($router[$key]);
		}

		return $return;
	}

}
