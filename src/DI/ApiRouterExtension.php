<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter\DI;

use Ublaboo\ApiRouter\ApiRoute;
use Nette\Reflection\ClassType;
use Nette;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Annotations\Reader;

class ApiRouterExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	private $defaults = [
		'ignoreAnnotation' => []
	];

	/**
	 * @var Reader
	 */
	private $reader;


	/**
	 * @return void
	 */
	public function beforeCompile()
	{
		$config = $this->_getConfig();

		$builder = $this->getContainerBuilder();
		$compiler_config = $this->compiler->getConfig();

		$this->setupReaderAnnotations($config);
		$this->setupReader($compiler_config);

		$routes = $this->findRoutes($builder);

		$builder->addDefinition($this->prefix('resolver'))
			->setClass('Ublaboo\ApiRouter\DI\ApiRoutesResolver')
			->addSetup('prepandRoutes', [$builder->getDefinition('router'), $routes])
			->addTag('run');
	}


	/**
	 * [setupReaderAnnotations description]
	 * @param  array $config
	 * @return void
	 */
	private function setupReaderAnnotations($config)
	{
		/**
		 * Prepare AnnotationRegistry
		 */
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRoute.php');
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRouteSpec.php');

		AnnotationReader::addGlobalIgnoredName('persistent');
		AnnotationReader::addGlobalIgnoredName('inject');

		foreach ($config['ignoreAnnotation'] as $ignore) {
			AnnotationReader::addGlobalIgnoredName($ignore);
		}
	}


	/**
	 * @param  array $compiler_config
	 * @return void
	 */
	private function setupReader($compiler_config)
	{
		$cache_path = $compiler_config['parameters']['tempDir'] . '/cache/ApiRouter.Annotations';

		/**
		 * Prepare AnnotationReader - use cached values
		 */
		$this->reader = new CachedReader(
			new AnnotationReader,
			new FilesystemCache($cache_path),
			$compiler_config['parameters']['debugMode']
		);
	}


	/**
	 * @param  Nette\DI\ContainerBuilder $builder
	 * @return array
	 */
	private function findRoutes(Nette\DI\ContainerBuilder $builder)
	{
		/**
		 * Find all presenters and their routes
		 */
		$presenters = $builder->findByTag('nette.presenter');
		$routes = [];

		foreach ($presenters as $presenter) {
			$this->findRoutesInPresenter($presenter, $routes);
		}

		/**
		 * Return routes sorted by priority
		 */
		return $this->sortByPriority($routes);
	}


	/**
	 * @param  string $presenter
	 * @param  array $routes
	 * @return void
	 */
	private function findRoutesInPresenter($presenter, & $routes)
	{
		$r = ClassType::from($presenter);

		$route = $this->reader->getClassAnnotation($r, ApiRoute::class);

		if (!$route) {
			return [];
		}

		/**
		 * Add route to priority-half-sorted list
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
			$this->findPresenterMethodRoute($method_r, $routes, $route);
		}

		/**
		 * Add ApiRouter annotated presenter route only if there are some remaining
		 * methods without ApiRouter annotated presenter method
		 */
		if (!empty($route->getMethods())) {
			$routes[$route->getPriority()][] = $route;
		}

		$routes[$route->getPriority()][] = $route;
	}


	/**
	 * @param  \ReflectionMethod $method_reflection
	 * @param  array             $routes
	 * @param  ApiRoute          $route
	 * @return void
	 */
	private function findPresenterMethodRoute(\ReflectionMethod $method_reflection, & $routes, ApiRoute $route)
	{
		$action_route = $this->reader->getMethodAnnotation($method_reflection, ApiRoute::class);

		/**
		 * Get action without that ^action string
		 */
		$action = lcfirst(preg_replace('/^action/', '', $method_reflection->name));

		/**
		 * Route can be defined also for particular action
		 */
		if (!$action_route) {
			$route->setAction($action);

			return;
		}

		if ($method_reflection instanceof Nette\Reflection\Method) {
			$action_route->setDescription($method_reflection->getAnnotation('description'));
		}

		/**
		 * Action route will inherit presenter name, priority, etc from parent route
		 */
		$action_route->setPresenter($action_route->getPresenter() ?: $route->getPresenter());
		$action_route->setPriority($action_route->getPriority() ?: $route->getPriority());
		$action_route->setFormat($action_route->getFormat() ?: $route->getFormat());
		$action_route->setSection($action_route->getSection() ?: $route->getSection());
		$action_route->setAction($action, $action_route->getMethod() ?: NULL);

		$routes[$route->getPriority()][] = $action_route;
	}


	/**
	 * @param  array $routes
	 * @return array
	 */
	private function sortByPriority(array $routes)
	{
		$return = [];

		foreach ($routes as $priority => $priority_routes) {
			foreach ($priority_routes as $route) {
				$return[] = $route;
			}
		}

		return $return;
	}


	/**
	 * @return array
	 */
	private function _getConfig()
	{
		$config = $this->validateConfig($this->defaults, $this->config);

		if (!is_array($config['ignoreAnnotation'])) {
			$config['ignoreAnnotation'] = [$config['ignoreAnnotation']];
		}

		return (array) $config;
	}

}
