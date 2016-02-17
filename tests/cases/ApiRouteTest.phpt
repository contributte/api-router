<?php

namespace Ublaboo\ApiDocu\Tests\Cases;

use Tester\TestCase,
	Tester\Assert,
	Mockery,
	Nette,
	Ublaboo\ApiRouter\ApiRoute;

require __DIR__ . '/../bootstrap.php';

final class ApiRouteTest extends TestCase
{

	public function testActionsMethods()
	{
		$route = new ApiRoute('/u', 'U');

		Assert::same(['POST', 'GET', 'PUT', 'DELETE'], $route->getMethods());

		$route = new ApiRoute('/u', 'U', [
			'methods' => ['POST' => 'create']
		]);

		Assert::same(['POST'], $route->getMethods());

		$route->setAction('foo', 'POST');
		$route->setAction('bar');
		$route->setAction('create');
		$route->setAction('read', 'GET');
		$route->setAction('baz', 'BAR');

		Assert::same(['POST', 'GET'], $route->getMethods());
	}


	public function testPlacehodlerParameters()
	{
		$route = new ApiRoute('/u/<id>[/<l>-<r>/<aa>]/<a>', 'U');

		Assert::same(['id', 'l', 'r', 'aa', 'a'], $route->getPlacehodlerParameters());
		Assert::same(['id', 'a'], $route->getRequiredParams());
	}


	public function testResolveMethod()
	{
		$headers = NULL;
		$method = 'GET';

		$u = new Nette\Http\UrlScript('http://foo.com/users');
		$r = new Nette\Http\Request($u, NULL, NULL, NULL, NULL, $headers, $method, NULL, NULL, NULL);

		$route = new ApiRoute('/users', 'U');

		Assert::same('GET', $route->resolveMethod($r));

		$headers = ['X-HTTP-Method-Override' => 'POST'];
		$method = 'GET';
		$r = new Nette\Http\Request($u, NULL, NULL, NULL, NULL, $headers, $method, NULL, NULL, NULL);

		Assert::same('POST', $route->resolveMethod($r));

		$u = new Nette\Http\UrlScript('http://foo.com/users?__apiRouteMethod=PUT');
		$r = new Nette\Http\Request($u, NULL, NULL, NULL, NULL, $headers, $method, NULL, NULL, NULL);

		Assert::same('POST', $route->resolveMethod($r));

		$u = new Nette\Http\UrlScript('http://foo.com/users?__apiRouteMethod=PUT');
		$r = new Nette\Http\Request($u, NULL, NULL, NULL, NULL, NULL, $method, NULL, NULL, NULL);

		Assert::same('PUT', $route->resolveMethod($r));
	}


	public function testMatch()
	{
		// Code here
	}

}


$test_case = new ApiRouteTest;
$test_case->run();
