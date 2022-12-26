<?php declare(strict_types = 1);

namespace Tests\Fixtures;

use Nette\Application\Routers\RouteList;

final class DummyRouterFactory
{

	public static function create(): RouteList
	{
		return new RouteList();
	}

}
