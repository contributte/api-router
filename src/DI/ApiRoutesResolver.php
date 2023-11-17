<?php declare(strict_types = 1);

namespace Contributte\ApiRouter\DI;

use ArrayAccess;
use Contributte\ApiRouter\Exception\ApiRouteWrongRouterException;
use Nette\Application\Routers\RouteList;
use Nette\Routing\Route;
use Nette\Routing\Router;
use Traversable;

class ApiRoutesResolver
{

	/**
	 * Place REST API routes at the beginnig of all routes
	 *
	 * @param Route[] $routes
	 */
	public function prepandRoutes(Router $router, array $routes): void
	{
		if ($routes === []) {
			return;
		}

		if (!($router instanceof Traversable) || !($router instanceof ArrayAccess)) {
			throw new ApiRouteWrongRouterException(sprintf(
				'ApiRoutesResolver can not add ApiRoutes to your router. Use for example %s instead',
				RouteList::class
			));
		}

		$userRoutes = $this->findAndDestroyUserRoutes($router);

		/**
		 * Add ApiRoutes first
		 */
		foreach ($routes as $route) {
			$router[] = $route;
		}

		/**
		 * User routes on second place
		 */
		foreach ($userRoutes as $route) {
			$router[] = $route;
		}
	}

	/**
	 * @return array<int, Router>
	 */
	public function findAndDestroyUserRoutes(Router $router): array
	{
		$keys = [];
		$return = [];

		if ($router instanceof RouteList) {
			foreach ($router->getRouters() as $key => $route) {
				$return[] = $route;
				$keys[] = $key;
			}
		}

		foreach (array_reverse($keys) as $key) {
			unset($router[$key]);
		}

		return $return;
	}

}
