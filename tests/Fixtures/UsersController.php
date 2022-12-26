<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Contributte\ApiRouter\ApiRoute;
use Nette\Application\UI\Presenter;

/**
 * @ApiRoute(
 *    "/api/users[/<id>]",
 *    parameters={
 *        "id"={
 *            "requirement": "\d+",
 *            "default": 10
 *        }
 *    }
 * )
 */
class UsersController extends Presenter
{

	/**
	 * Get user detail
	 *
	 * @ApiRoute(
	 *    "/api/users/<id>[/<foo>-<bar>]",
	 *    parameters={
	 *        "id"={
	 *            "requirement": "\d+"
	 *        }
	 *    },
	 *    method="GET"
	 * )
	 */
	public function actionRead($id, $foo = null, $bar = null)
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
