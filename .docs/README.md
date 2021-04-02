# Contributte / ApiRouter

## Content

- [Installation](#installation)
- [Usage](#usage)
    + [Using annotation](#using-annotation)
    + [Using Nette Router](#using-nette-router)
    + [Api documentation](#api-documentation)

## Installation

ApiRouter is available on composer:

```bash
composer require contributte/api-router
```

## Usage

### Using annotation

```php
namespace App\ResourcesModule\Presenters;

use Nette;
use Contributte\ApiRouter\ApiRoute;

/**
 * API for managing users
 * 
 * @ApiRoute(
 * 	"/api-router/api/users[/<id>]",
 * 	parameters={
 * 		"id"={
 * 			"requirement": "\d+",
 * 			"default": 10
 * 		}
 * 	},
 *  priority=1,
 *  presenter="Resources:Users"
 * )
 */
class UsersPresenter extends Nette\Application\UI\Presenter
{

	/**
	 * Get user detail
	 * 
	 * @ApiRoute(
	 * 	"/api-router/api/users/<id>[/<foo>-<bar>]",
	 * 	parameters={
	 * 		"id"={
	 * 			"requirement": "\d+"
	 * 		}
	 * 	},
	 * 	method="GET",
	 * 	}
	 * )
	 */
	public function actionRead($id, $foo = NULL, $bar = NULL)
	{
		$this->sendJson(['id' => $id, 'foo' => $foo, 'bar' => $bar]);
	}


	public function actionUpdate($id)
	{
		$this->sendJson(['id' => $id]);
	}


	public function actionDelete($id)
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

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Contributte\ApiRouter\ApiRoute;

class RouterFactory
{

	/**
	 * @return Nette\Application\IRouter
	 */
	public function createRouter()
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
		$router[] = new ApiRoute('/hello', 'ApiRouter', [
			'methods' => ['GET' => 'superRead', 'POST']
		]);

		$router[] = new ApiRoute('/api-router/api/users[/<id>]', 'Resources:Users', [
			'parameters' => [
				'id' => ['requirement' => '\d+', 'default' => 10]
			],
			'priority' => 1
		]);

		$router[] = new ApiRoute('/api-router/api/users/<id>[/<foo>-<bar>]', 'Resources:Users', [
			'parameters' => [
				'id' => ['requirement' => '\d+']
			],
			'priority' => 1
		]);

		$router[] = new Route('<presenter>/<action>', 'Homepage:default');

		return $router;
	}
}
```

### Api documentation

There is another extension for Nette which works pretty well with ApiRouter: [ApiDocu](https://github.com/contributte/api-docu).
ApiDocu generates awesome api documentation from your RESTful routes. It can also show you documentation in application runtime!
