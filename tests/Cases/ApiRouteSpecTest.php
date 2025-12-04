<?php declare(strict_types = 1);

namespace Tests\Cases;

use Contributte\ApiRouter\ApiRoute;
use Contributte\ApiRouter\Exception\ApiRouteWrongPropertyException;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
final class ApiRouteSpecTest extends TestCase
{

	public function testParameters(): void
	{
		Assert::exception(
			function (): void {
				new ApiRoute('/users', 'Users', parameters: ['id' => ['type' => 'integer']]);
			},
			ApiRouteWrongPropertyException::class,
			'Parameter <id> is not present in the url mask'
		);

		Assert::exception(
			function (): void {
				new ApiRoute('/users/<id>', 'Users', parameters: ['id' => ['foo' => 'integer']]);
			},
			ApiRouteWrongPropertyException::class,
			'You cat set only these description informations: [requirement, type, description, default] - "foo" given'
		);

		Assert::exception(
			function (): void {
				new ApiRoute('/users/<id>', 'Users', parameters: ['id' => ['type' => []]]);
			},
			ApiRouteWrongPropertyException::class,
			'You cat set only scalar parameters informations (key [type])'
		);
	}

	public function testTags(): void
	{
		$route = new ApiRoute('/u', 'Users', tags: ['public', 'secured' => '#e74c3c']);

		Assert::same(['public' => '#9b59b6', 'secured' => '#e74c3c'], $route->getTags());
	}

}

(new ApiRouteSpecTest())->run();
