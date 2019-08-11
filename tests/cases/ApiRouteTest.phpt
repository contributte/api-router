<?php

namespace Ublaboo\ApiDocu\Tests\Cases;

use Tester\TestCase,
	Tester\Assert,
	Mockery,
	Nette,
	Ublaboo\ApiRouter\ApiRoute,
	Nette\Http\UrlScript,
	Nette\Http\Request,
	Nette\Application\Request as AppRq;

require __DIR__ . '/../bootstrap.php';

final class ApiRouteTest extends TestCase
{

	public function testActionsMethods()
	{
		$route = new ApiRoute('/u', 'U');

		Assert::same(['POST', 'GET', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'], $route->getMethods());

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

		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u, NULL, NULL, NULL, NULL, $headers, $method);

		$route = new ApiRoute('/users', 'U');

		Assert::same('GET', $route->resolveMethod($r));

		$headers = ['X-HTTP-Method-Override' => 'POST'];
		$method = 'GET';
		$r = new Request($u, NULL, NULL, NULL, $headers, $method);

		Assert::same('POST', $route->resolveMethod($r));

		$u = new UrlScript('http://foo.com/users?__apiRouteMethod=PUT');
		$r = new Request($u, NULL, NULL, NULL, $headers, $method);

		Assert::same('POST', $route->resolveMethod($r));

		$u = new UrlScript('http://foo.com/users?__apiRouteMethod=PUT');
		$r = new Request($u, NULL, NULL, NULL, NULL, $method);

		Assert::same('PUT', $route->resolveMethod($r));
	}


	public function testMatchMethods()
	{
		$route = new ApiRoute('/users', 'U', ['methods' => ['POST' => 'create']]);
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u, NULL, NULL, NULL, NULL, 'GET');

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users', 'U', ['methods' => ['GET' => 'read']]);
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u, NULL, NULL, NULL, NULL, 'POST');

		Assert::same(NULL, $route->match($r));
	}


	public function testMatchUrl()
	{
		$route = new ApiRoute('/users/', 'U');
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users', 'U');
		$u = new UrlScript('http://foo.com/users/');
		$r = new Request($u);

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users[/]', 'U');
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::notSame(NULL, $route->match($r));

		$route = new ApiRoute('/users[/]', 'U');
		$u = new UrlScript('http://foo.com/users/');
		$r = new Request($u);

		Assert::notSame(NULL, $route->match($r));
	}


	public function testMatchParameters()
	{
		$route = new ApiRoute('/users/<id>', 'U');
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users/<id>', 'U');
		$u = new UrlScript('http://foo.com/users/aaaa');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same('aaaa', $appRq['id']);

		$route = new ApiRoute('/users[/<id>]', 'U');
		$u = new UrlScript('http://foo.com/users/aaaa');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same('aaaa', $appRq['id']);

		$route = new ApiRoute('/users[/<id>]', 'U');
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(NULL, $appRq['id']);

		$route = new ApiRoute('/users/<l>-<p>[/<id>/<a>]', 'U');
		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users/<l>-<p>[/<id>/<a>]', 'U');
		$u = new UrlScript('http://foo.com/users/a');
		$r = new Request($u);

		Assert::same(NULL, $route->match($r));

		$route = new ApiRoute('/users/<l>-<p>[/<id>/<a>]', 'U');
		$u = new UrlScript('http://foo.com/users/l-p');
		$r = new Request($u);

		Assert::notSame(NULL, $route->match($r));

		$route = new ApiRoute('/users/<l>-<p>[/<id>/<a>]', 'U');
		$u = new UrlScript('http://foo.com/users/l-p/8/aa');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same('l', $appRq['l']);
		Assert::same('p', $appRq['p']);
		Assert::same(8, (int) $appRq['id']);
		Assert::same('aa', $appRq['a']);

		$route = new ApiRoute('/users/<l>-<p>[/<id>/<a>]', 'U');
		$u = new UrlScript('http://foo.com/users/l-p/8/aa?bubla=a');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same('a', $appRq['bubla']);
		Assert::same('U', $appRq['presenter']);


		$route = new ApiRoute('/users[/<id>][/<foo>][/<bar>]', 'U');

		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(NULL, $appRq['id']);
		Assert::same(NULL, $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same(NULL, $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1/foo');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same('foo', $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1/foo/bar');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same('foo', $appRq['foo']);
		Assert::same('bar', $appRq['bar']);


		$route = new ApiRoute('/users[/<id>[/<foo>[/<bar>]]]', 'U');

		$u = new UrlScript('http://foo.com/users');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(NULL, $appRq['id']);
		Assert::same(NULL, $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same(NULL, $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1/foo');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same('foo', $appRq['foo']);
		Assert::same(NULL, $appRq['bar']);

		$u = new UrlScript('http://foo.com/users/1/foo/bar');
		$r = new Request($u);

		Assert::notSame(NULL, $appRq = $route->match($r));
		Assert::same(1, (int) $appRq['id']);
		Assert::same('foo', $appRq['foo']);
		Assert::same('bar', $appRq['bar']);
	}


	public function testConstructUrl()
	{
		$r = (new AppRq('Reources:Users'))->toArray();
		$u = new UrlScript('http://foo.com/users');
		$route = new ApiRoute('/users/<id>', 'U');

		Assert::same(NULL, $route->constructUrl($r, $u));

		$r = (new AppRq('Reources:Users', 'GET', ['id' => 8]))->toArray();
		$u = new UrlScript('http://foo.com/users');
		$route = new ApiRoute('/users/<id>', 'U');

		Assert::same(NULL, $route->constructUrl($r, $u));

		$r = (new AppRq('Resources:Users', 'GET', ['id' => 8, 'action' => 'create']))->toArray();
		$u = new UrlScript('http://foo.com/');
		$route = new ApiRoute('/users/<id>', 'Resources:Users');

		Assert::same('http://foo.com/users/8', $route->constructUrl($r, $u));

		$r = (new AppRq('Resources:Users', 'GET', ['id' => 8, 'action' => 'create', 'f' => 'a', 'b' => 'a']))->toArray();
		$u = new UrlScript('http://foo.com/');
		$route = new ApiRoute('/users/<id>[/<f>-<b>]', 'Resources:Users');

		Assert::same('http://foo.com/users/8/a-a', $route->constructUrl($r, $u));
	}

}


$test_case = new ApiRouteTest;
$test_case->run();
