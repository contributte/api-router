<?php

declare(strict_types=1);

namespace Tests\Cases;

use Contributte\ApiRouter\ApiRoute;
use Contributte\ApiRouter\DI\ApiRoutesResolver;
use Contributte\ApiRouter\Exception\ApiRouteWrongRouterException;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class ApiRoutesResolverTest extends TestCase
{

	public function testRouteList(): void
	{
		$router = new RouteList();

		$router[] = new Route('/a', 'Users:');

		$api_routes = [new ApiRoute('/u', 'Users')];

		$resolver = new ApiRoutesResolver();

		$resolver->prepandRoutes($router, $api_routes);

		$order = [];

		foreach ($router->getRouters() as $route) {
			$order[] = $route;
		}

		Assert::true($order[0] instanceof ApiRoute);
		Assert::true($order[1] instanceof Route);
	}


	public function testRoute(): void
	{
		$router = new Route('/a', 'Users:');

		$api_routes = [new ApiRoute('/u', 'Users')];

		$resolver = new ApiRoutesResolver();

		try {
			$resolver->prepandRoutes($router, $api_routes);

			/**
			 * This point can not be reached
			 */
			Assert::false(true);
		} catch (\Throwable $e) {
			Assert::true($e instanceof ApiRouteWrongRouterException);
		}
	}

}


$test_case = new ApiRoutesResolverTest();
$test_case->run();
