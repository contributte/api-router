<?php

namespace Ublaboo\ApiDocu\Tests\Cases;

use Tester\TestCase,
	Tester\Assert,
	Mockery,
	Ublaboo\ApiRouter\ApiRoute;

require __DIR__ . '/../bootstrap.php';

final class ApiRouteSpecTest extends TestCase
{

	public function testConstructor()
	{
		Assert::exception(function(){
			new ApiRoute('/users', 'Users', ['foo' => 'boo']);
		},
			'Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException',
			'Unknown property "foo" on annotation "Ublaboo\ApiRouter\ApiRoute"'
		);
	}


	public function testParameters()
	{
		Assert::exception(function(){
			new ApiRoute('/users', 'Users', ['parameters' => ['id' => ['type' => 'integer']]]);
		},
			'Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException',
			'Parameter <id> is not present in the url mask'
		);

		Assert::exception(function(){
			new ApiRoute('/users/<id>', 'Users', ['parameters' => ['id' => ['foo' => 'integer']]]);
		},
			'Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException',
			'You cat set only these description informations: [requirement, type, description, default] - "foo" given'
		);

		Assert::exception(function(){
			new ApiRoute('/users/<id>', 'Users', ['parameters' => ['id' => ['type' => []]]]);
		},
			'Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException',
			'You cat set only scalar parameters informations (key [type])'
		);
	}


	public function testTags()
	{
		$route = new ApiRoute('/u', 'Users', ['tags' => ['public', 'secured' => '#e74c3c']]);

		Assert::same(['public' => "#9b59b6", 'secured' => "#e74c3c"], $route->getTags());
	}

}


$test_case = new ApiRouteSpecTest;
$test_case->run();
