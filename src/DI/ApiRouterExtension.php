<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter\DI;

use Ublaboo\ApiRouter\ApiRoute;
use Ublaboo\ApiRouter\ApiRouteSpec;
use Nette\Reflection\ClassType;
use Nette;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\FileCacheReader;

class ApiRouterExtension extends Nette\DI\CompilerExtension
{

	private $defaults = [
		
	];


	public function loadConfiguration()
	{
		$this->config = $this->_getConfig();
	}


	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->compiler->getConfig();

		$routes = $this->findRoutes($builder, $config);

		foreach ($routes as $route) {
			$router = $builder->getDefinition('router');

			$router->addSetup('offsetSet', [NULL, $route]);
		}
	}


	private function _getConfig()
	{
		$config = $this->validateConfig($this->defaults, $this->config);

		return $config;
	}


	private function findRoutes($builder, $config)
	{
		/**
		 * Prepare AnnotationRegistry
		 */
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRoute.php');
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRouteSpec.php');

		AnnotationReader::addGlobalIgnoredName('persistent');

		/**
		 * Prepare AnnotationReader - use cached values
		 */
		$reader = new FileCacheReader(
			new AnnotationReader,
			$config['parameters']['tempDir'] . '/cache/ApiRouter.Annotations',
			$debug = $config['parameters']['debugMode']
		);

		/**
		 * Find all presenters and their routes
		 */
		$presenter = $builder->findByTag('nette.presenter');
		$routes = [];
		$default = [
			'POST'   => 'create',
			'GET'    => 'read',
			'PUT'    => 'update',
			'DELETE' => 'delete'
		];

		foreach ($presenter as $presenter) {
			$r = ClassType::from($presenter);

			$route = $reader->getClassAnnotation($r, ApiRoute::class);

			if ($route) {
				/**
				 * Add route to priority-sorted list
				 */
				if (empty($routes[$route->getPriority()])) {
					$routes[$route->getPriority()] = [];
				}

				$route->setDescription($r->getAnnotation('description'));

				if (!$route->getPresenter()) {
					$route->setPresenter(preg_replace('/Presenter$/', '', $r->getShortName()));
				}

				/**
				 * Find apropriate methods
				 */
				foreach ($r->getMethods() as $method_r) {
					$route_action = $reader->getMethodAnnotation($method_r, ApiRoute::class);

					/**
					 * Get action without that ^action string
					 */
					$action = lcfirst(preg_replace('/^action/', '', $method_r->getName()));

					/**
					 * Route can be defined also for perticular action
					 */
					if ($route_action) {
						$route_action->setDescription($method_r->getAnnotation('description'));

						/**
						 * Action route will inherit presenter name nad priority from parent route
						 */
						if (!$route_action->getPresenter()) {
							$route_action->setPresenter($route->getPresenter());
						}

						if (!$route_action->getPriority()) {
							$route_action->setPriority($route->getPriority());
						}

						if (!$route_action->getFormat()) {
							$route_action->setFormat($route->getFormat());
						}

						if (!$route_action->getSection()) {
							$route_action->setSection($route->getSection());
						}

						if ($route_action->getMethod()) {
							$route_action->setAction($route_action->getMethod(), $action);
						} else {
							if ($method = array_search($action, $default)) {
								$route_action->setAction($method, $action);
							}
						}

						$routes[$route->getPriority()][] = $route_action;
					} else {
						if ($method = array_search($action, $default)) {
							$route->setAction($method, $action);
						}
					}
				}

				$routes[$route->getPriority()][] = $route;
			}
		}

		/**
		 * Return routes sorted by priority
		 */
		$return = [];

		foreach ($routes as $priority => $priority_routes) {
			foreach ($priority_routes as $route) {
				$return[] = $route;
			}
		}

		return $return;
	}

}
