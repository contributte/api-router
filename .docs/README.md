# Contributte / ApiRouter

## Content

- [Installation](#installation)
- [Usage](#usage)
    + [Using attributes](#using-attributes)
    + [Using Nette Router](#using-nette-router)
    + [Api documentation](#api-documentation)
- [Examples](#examples)

## Installation

ApiRouter is available on composer:

```bash
composer require contributte/api-router
```

## Usage

### Configure

At first register compiler extension.

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

Example of used attributes. Don't forget to import `Contributte\ApiRouter\ApiRoute`.

```php
namespace App\ResourcesModule\Presenters;

use Nette\Application\UI\Presenter;
use Contributte\ApiRouter\ApiRoute;

/**
 * API for managing users
 */
#[ApiRoute(
	'/api-router/api/users[/<id>]',
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
		'/api-router/api/users/<id>[/<foo>-<bar>]',
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
		$router[] = new ApiRoute('/hello', 'Users');

		/**
		 * Custom matching:
		 * 	GET  => UsersPresenter::actionSuperRead()
		 * 	POST => UsersPresenter::actionCreate()
		 */
		$router[] = new ApiRoute('/hello', 'ApiRouter', methods: ['GET' => 'superRead', 'POST']);

		$router[] = new ApiRoute(
			'/api-router/api/users[/<id>]',
			'Resources:Users',
			parameters: [
				'id' => ['requirement' => '\d+', 'default' => 10],
			],
			priority: 1,
		);

		$router[] = new ApiRoute(
			'/api-router/api/users/<id>[/<foo>-<bar>]',
			'Resources:Users',
			parameters: [
				'id' => ['requirement' => '\d+'],
			],
			priority: 1,
		);

		# Disable basePath detection
		$route = new ApiRoute('/api-router/api/users', 'Resources:Users');
		$route->setAutoBasePath(false);
		$router[] = $route;

		$router[] = new Route('<presenter>/<action>', 'Homepage:default');

		return $router;
	}
}
```

### Api documentation

There is another extension for Nette which works pretty well with ApiRouter: [ApiDocu](https://github.com/contributte/api-docu).
ApiDocu generates awesome api documentation from your RESTful routes. It can also show you documentation in application runtime!

## Examples

We've made a few skeleton with preconfigured `contributte/api-router`.

https://github.com/contributte/api-router-skeleton
