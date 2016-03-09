<?php

namespace Ublaboo\ApiDocu\Tests\Cases;

use Tester\TestCase;
use Tester\Assert;
use Mockery;
use Ublaboo\ApiRouter\ApiRoute;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Ublaboo\ApiRouter\DI\ApiRoutesResolver;
use Ublaboo\ApiRouter\Exception\ApiRouteWrongRouterException;

require __DIR__ . '/../bootstrap.php';

final class ApiRoutesResolverTest extends TestCase
{

	public function testRouteList()
	{
		$router = new RouteList;

		$router[] = new Route('/a', 'Users:');

		$api_routes = [new ApiRoute('/u', 'Users')];

		$resolver = new ApiRoutesResolver;

		$resolver->prepandRoutes($router, $api_routes);

		$order = [];

		foreach ($router as $route) {
			$order[] = $route;
		}

		Assert::true($order[0] instanceof ApiRoute);
		Assert::true($order[1] instanceof Route);
	}


	public function testRoute()
	{
		$router = new Route('/a', 'Users:');

		$api_routes = [new ApiRoute('/u', 'Users')];

		$resolver = new ApiRoutesResolver;

		try {
			$resolver->prepandRoutes($router, $api_routes);

			/**
			 * This point can not be reached
			 */
			Assert::false(TRUE);
		} catch (\Exception $e) {
			Assert::true($e instanceof ApiRouteWrongRouterException);
		}
	}

}


$test_case = new ApiRoutesResolverTest;
$test_case->run();
