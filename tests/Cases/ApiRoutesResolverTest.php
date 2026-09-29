<?php declare(strict_types = 1);

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

		$router->add(new Route('/a', 'Users:'));

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

	public function testRouteListOrder(): void
	{
		$router = new RouteList();

		$userRoute1 = new Route('/a', 'Users:');
		$userRoute2 = new Route('/b', 'Users:old');
		$router->add($userRoute1);
		$router->add($userRoute2, RouteList::ONE_WAY);

		$apiRoute1 = new ApiRoute('/u', 'Users');
		$apiRoute2 = new ApiRoute('/v', 'Users');
		$apiRoute3 = new ApiRoute('/w', 'Users');

		$resolver = new ApiRoutesResolver();

		$resolver->prepandRoutes($router, [$apiRoute1, $apiRoute2, $apiRoute3]);

		Assert::same([$apiRoute1, $apiRoute2, $apiRoute3, $userRoute1, $userRoute2], $router->getRouters());

		// User one-way route stays one-way
		Assert::same([0, 0, 0, 0, RouteList::ONE_WAY], $router->getFlags());
	}

	public function testRouteListEmptyRoutes(): void
	{
		$router = new RouteList();

		$userRoute = new Route('/a', 'Users:');
		$router->add($userRoute, RouteList::ONE_WAY);

		$resolver = new ApiRoutesResolver();

		$resolver->prepandRoutes($router, []);

		Assert::same([$userRoute], $router->getRouters());
		Assert::same([RouteList::ONE_WAY], $router->getFlags());
	}

	public function testRoute(): void
	{
		$router = new Route('/a', 'Users:');

		$api_routes = [new ApiRoute('/u', 'Users')];

		$resolver = new ApiRoutesResolver();

		Assert::exception(
			static fn () => $resolver->prepandRoutes($router, $api_routes),
			ApiRouteWrongRouterException::class
		);
	}

}

(new ApiRoutesResolverTest())->run();
