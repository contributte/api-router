<?php

declare(strict_types=1);

namespace Contributte\ApiRouter\DI;

use Contributte\ApiRouter\ApiRoute;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Cache\FilesystemCache;
use Nette\Application\IPresenter;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\PhpGenerator\ClassType as GClassType;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use ReflectionMethod;

class ApiRouterExtension extends CompilerExtension
{

	/**
	 * @var array
	 */
	private $defaults = [
		'ignoreAnnotation' => [],
	];

	/**
	 * @var Reader
	 */
	private $reader;

	/**
	 * @var Definition
	 */
	private $definition;

	public function beforeCompile(): void
	{
		$config = $this->_getConfig();

		$builder = $this->getContainerBuilder();
		$compiler_config = $this->compiler->getConfig();

		$this->setupReaderAnnotations($config);
		$this->setupReader($compiler_config);

		$routes = $this->findRoutes($builder);

		$this->definition = $builder->addDefinition($this->prefix('resolver'))
			->setClass(ApiRoutesResolver::class)
			->addSetup('prepandRoutes', [$builder->getDefinition('router'), $routes]);
	}


	public function afterCompile(GClassType $class): void
	{
		parent::afterCompile($class);

		$class->getMethod('initialize')->addBody('$this->getService(?);', [$this->definition->getName()]);
	}


	private function setupReaderAnnotations(array $config): void
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


	private function setupReader(array $compiler_config): void
	{
		$cache_path = $compiler_config['parameters']['tempDir'] . '/cache/ApiRouter.Annotations';

		/**
		 * Prepare AnnotationReader - use cached values
		 */
		$this->reader = new CachedReader(
			new AnnotationReader(),
			new FilesystemCache($cache_path),
			$compiler_config['parameters']['debugMode']
		);
	}


	private function findRoutes(ContainerBuilder $builder): array
	{
		/**
		 * Find all presenters and their routes
		 */
		$presentersDec = $builder->findByType(IPresenter::class);
		$routes = [];

		foreach ($presentersDec as $presenterDec) {
			$this->findRoutesInPresenter($presenterDec->getType(), $routes);
		}

		/**
		 * Return routes sorted by priority
		 */
		return $this->sortByPriority($routes);
	}


	private function findRoutesInPresenter(string $presenter, array &$routes): void
	{
		$r = ClassType::from($presenter);

		$route = $this->reader->getClassAnnotation($r, ApiRoute::class);

		if (!$route) {
			return;
		}

		/**
		 * Add route to priority-half-sorted list
		 */
		if ($routes !== [] && !$routes[$route->getPriority()]) {
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
		if ($route->getMethods() !== []) {
			$routes[$route->getPriority()][] = $route;
		}
	}


	private function findPresenterMethodRoute(
		ReflectionMethod $method_reflection,
		array &$routes,
		ApiRoute $route
	): void
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

		if ($method_reflection instanceof Method) {
			$action_route->setDescription($method_reflection->getAnnotation('description'));
		}

		/**
		 * Action route will inherit presenter name, priority, etc from parent route
		 */
		$action_route->setPresenter($action_route->getPresenter() ?: $route->getPresenter());
		$action_route->setPriority($action_route->getPriority() ?: $route->getPriority());
		$action_route->setFormat($action_route->getFormat() ?: $route->getFormat());
		$action_route->setSection($action_route->getSection() ?: $route->getSection());
		$action_route->setAction($action, $action_route->getMethod() ?: null);

		$routes[$route->getPriority()][] = $action_route;
	}


	private function sortByPriority(array $routes): array
	{
		$return = [];

		foreach ($routes as $priority => $priority_routes) {
			foreach ($priority_routes as $route) {
				$return[] = $route;
			}
		}

		return $return;
	}


	private function _getConfig(): array
	{
		$config = $this->validateConfig($this->defaults, $this->config);

		if (!is_array($config['ignoreAnnotation'])) {
			$config['ignoreAnnotation'] = [$config['ignoreAnnotation']];
		}

		return (array) $config;
	}

}
