![](https://heatbadger.now.sh/github/readme/contributte/api-router/)

<p align=center>
  <a href="https://github.com/contributte/api-router/actions"><img src="https://badgen.net/github/checks/contributte/api-router/master"></a>
  <a href="https://codecov.io/gh/contributte/api-router"><img src="https://badgen.net/codecov/c/github/contributte/api-router"></a>
  <a href="https://packagist.org/packages/contributte/api-router"><img src="https://badgen.net/packagist/dm/contributte/api-router"></a>
  <a href="https://packagist.org/packages/contributte/api-router"><img src="https://badgen.net/packagist/v/contributte/api-router"></a>
</p>
<p align=center>
  <a href="https://packagist.org/packages/contributte/api-router"><img src="https://badgen.net/packagist/php/contributte/api-router"></a>
  <a href="https://github.com/contributte/api-router"><img src="https://badgen.net/github/license/contributte/api-router"></a>
  <a href="https://bit.ly/ctteg"><img src="https://badgen.net/badge/support/gitter/cyan"></a>
  <a href="https://bit.ly/cttfo"><img src="https://badgen.net/badge/support/forum/yellow"></a>
  <a href="https://contributte.org/partners.html"><img src="https://badgen.net/badge/sponsor/donations/F96854"></a>
</p>

<p align=center>
Website 🚀 <a href="https://contributte.org">contributte.org</a> | Contact 👨🏻‍💻 <a href="https://f3l1x.io">f3l1x.io</a> | Twitter 🐦 <a href="https://twitter.com/contributte">@contributte</a>
</p>

Attribute-friendly API routing for Nette applications with HTTP method mapping and RESTful route helpers.

## Versions

| State  | Version | Branch   | Nette  | PHP     |
|--------|---------|----------|--------|---------|
| dev    | `^7.1`  | `master` | `4.0+` | `>=8.2` |
| stable | `^7.0`  | `master` | `4.0+` | `>=8.2` |

## Content

- [Installation](#installation)
- [Usage](#usage)
  - [Configure](#configure)
  - [Using attributes](#using-attributes)
  - [Using Nette Router](#using-nette-router)
  - [API documentation](#api-documentation)
- [Examples](#examples)
- [Development](#development)

## Installation

To install the latest version of `contributte/api-router` use [Composer](https://getcomposer.org).

```bash
composer require contributte/api-router
```

## Usage

### Configure

First, register the compiler extension.

```neon
extensions:
	apiRouter: Contributte\ApiRouter\DI\ApiRouterExtension
```

Don't forget to register your controller/presenter/endpoint classes.

```neon
services:
	- App\Controllers\LoginController
	- App\Controllers\PingController
```

### Using attributes

Example of attribute usage. Don't forget to import `Contributte\ApiRouter\ApiRoute`.

```php
namespace App\ResourcesModule\Presenters;

use Nette\Application\UI\Presenter;
use Contributte\ApiRouter\ApiRoute;

/**
 * API for managing users
 */
#[ApiRoute(
	path: '/api-router/api/users[/<id>]',
	parameters: [
		'id' => [
			'requirement' => '\d+',
			'default' => 10,
		],
	],
	priority: 1,
	presenter: 'Resources:Users',
)]
class UsersController extends Presenter
{

	/**
	 * Get user detail
	 */
	#[ApiRoute(
		path: '/api-router/api/users/<id>[/<foo>-<bar>]',
		parameters: [
			'id' => [
				'requirement' => '\d+',
			],
		],
		method: 'GET',
	)]
	public function actionRead(int $id, ?string $foo = null, ?string $bar = null): void
	{
		$this->sendJson(['id' => $id, 'foo' => $foo, 'bar' => $bar]);
	}


	public function actionUpdate(int $id): void
	{
		$this->sendJson(['id' => $id]);
	}


	public function actionDelete(int $id): void
	{
		$this->sendJson(['id' => $id]);
	}
}
```

Now 3 routes will be created (well, 2, but the one accepts both PUT and DELETE method).

If you don't want to create route with DELETE method, simply remove the `UsersPresenter::actionDelete()` method.

### Using Nette Router

```php
namespace App;

use Contributte\ApiRouter\ApiRoute;
use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;

class RouterFactory
{

	public function createRouter(): Nette\Routing\RouteList
	{
		$router = new RouteList;

		/**
		 * Simple route with matching (only if methods below exist):
		 * 	GET     => UsersPresenter::actionRead()
		 * 	POST    => UsersPresenter::actionCreate()
		 * 	PUT     => UsersPresenter::actionUpdate()
		 * 	DELETE  => UsersPresenter::actionDelete()
		 */
		$router[] = new ApiRoute(path: '/hello', presenter: 'Users');

		/**
		 * Custom matching:
		 * 	GET  => UsersPresenter::actionSuperRead()
		 * 	POST => UsersPresenter::actionCreate()
		 */
		$router[] = new ApiRoute(path: '/hello', presenter: 'ApiRouter', methods: ['GET' => 'superRead', 'POST']);

		$router[] = new ApiRoute(
			path: '/api-router/api/users[/<id>]',
			presenter: 'Resources:Users',
			parameters: [
				'id' => ['requirement' => '\d+', 'default' => 10],
			],
			priority: 1,
		);

		$router[] = new ApiRoute(
			path: '/api-router/api/users/<id>[/<foo>-<bar>]',
			presenter: 'Resources:Users',
			parameters: [
				'id' => ['requirement' => '\d+'],
			],
			priority: 1,
		);

		# Disable basePath detection
		$route = new ApiRoute(path: '/api-router/api/users', presenter: 'Resources:Users');
		$route->setAutoBasePath(false);
		$router[] = $route;

		$router[] = new Route('<presenter>/<action>', 'Homepage:default');

		return $router;
	}
}
```

### API documentation

There is another extension for Nette which works pretty well with ApiRouter: [ApiDocu](https://github.com/contributte/api-docu).
ApiDocu generates API documentation from your RESTful routes and can show it in the application runtime.

## Examples

We provide a skeleton preconfigured with `contributte/api-router`.

https://github.com/contributte/api-router-skeleton

## Development

See [how to contribute](https://contributte.org/contributing.html) to this package.

This package is currently maintained by these authors.

<a href="https://github.com/paveljanda">
  <img width="80" height="80" src="https://avatars2.githubusercontent.com/u/1488874?v=3&s=80">
</a>

-----

Consider to [support](https://contributte.org/partners.html) **contributte** development team.
Also thank you for using this package.
