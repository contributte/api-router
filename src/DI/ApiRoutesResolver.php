<?php declare(strict_types = 1);

namespace Contributte\ApiRouter\DI;

use ArrayAccess;
use Contributte\ApiRouter\Exception\ApiRouteWrongRouterException;
use Nette\Application\Routers\RouteList;
use Nette\Routing\RouteList as NetteRouteList;
use Nette\Routing\Router;

class ApiRoutesResolver
{

	/**
	 * Place REST API routes at the beginnig of all routes
	 *
	 * @param array<Router> $routes
	 */
	public function prepandRoutes(Router $router, array $routes): void
	{
		if ($routes === []) {
			return;
		}

		/**
		 * Prepend ApiRoutes in reverse order, so they keep their order
		 * and user routes (including their flags) stay untouched
		 */
		if ($router instanceof NetteRouteList) {
			foreach (array_reverse($routes) as $route) {
				$router->prepend($route);
			}

			return;
		}

		if (!($router instanceof ArrayAccess)) {
			throw new ApiRouteWrongRouterException(sprintf(
				'ApiRoutesResolver can not add ApiRoutes to your router. Use for example %s instead',
				RouteList::class
			));
		}

		/**
		 * Other ArrayAccess routers - just add ApiRoutes
		 */
		foreach ($routes as $route) {
			$router[] = $route;
		}
	}

	/**
	 * @deprecated Not used anymore, ApiRoutesResolver::prepandRoutes() uses RouteList::prepend();
	 *             triggers deprecations on nette/application >= 3.3; will be removed in next major
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
			unset($router[$key]); // @phpstan-ignore-line
		}

		return $return;
	}

}
