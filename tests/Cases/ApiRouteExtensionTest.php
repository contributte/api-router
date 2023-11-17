<?php declare(strict_types = 1);

namespace Tests\Cases;

use Contributte\ApiRouter\DI\ApiRouterExtension;
use Nette\Application\Routers\RouteList;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Tester\Assert;
use Tester\TestCase;
use Tests\Toolkit\Helpers;
use Tests\Toolkit\Tests;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class ApiRouteExtensionTest extends TestCase
{

	private Container $container;

	public function testRouter(): void
	{
		/** @var RouteList $router */
		$router = $this->container->getByType(RouteList::class);

		Assert::count(2, $router->getRouters());
	}

	protected function setUp(): void
	{
		parent::setUp();

		$loader = new ContainerLoader(Tests::TEMP_PATH, true);
		$class = $loader->load(function (Compiler $compiler): void {
			$compiler->addExtension('api', new ApiRouterExtension());
			$compiler->addConfig(Helpers::neon('
			services:
				router: Tests\Fixtures\DummyRouterFactory::create
				- Tests\Fixtures\UsersController
			'));
			$compiler->addConfig([
				'parameters' => [
					'debugMode' => false,
					'tempDir' => Tests::TEMP_PATH,
				],
			]);
		}, __FILE__ . time());

		$this->container = new $class();
		$this->container->initialize();
	}

}

(new ApiRouteExtensionTest())->run();
