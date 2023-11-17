<?php declare(strict_types = 1);

namespace Contributte\ApiRouter\DI;

use Contributte\ApiRouter\ApiRoute;
use Contributte\Utils\Annotations;
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
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use ReflectionClass;
use ReflectionMethod;
use stdClass;

/**
 * @property-read stdClass $config
 */
class ApiRouterExtension extends CompilerExtension
{

	private ?Reader $reader = null;

	private ?Definition $definition = null;

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'ignoreAnnotation' => Expect::array([]),
		]);
	}

	public function beforeCompile(): void
	{
		$config = $this->getConfig();

		$builder = $this->getContainerBuilder();
		$compilerConfig = $this->compiler->getConfig();

		$this->setupReaderAnnotations($config);
		$this->setupReader($compilerConfig);

		$routes = $this->findRoutes($builder);

		$this->definition = $builder->addDefinition($this->prefix('resolver'))
			->setType(ApiRoutesResolver::class)
			->addSetup('prepandRoutes', [$builder->getDefinition('router'), $routes]);
	}

	public function afterCompile(GClassType $class): void
	{
		parent::afterCompile($class);

		$class->getMethod('initialize')->addBody('$this->getService(?);', [$this->definition->getName()]);
	}

	private function setupReaderAnnotations(stdClass $config): void
	{
		/**
		 * Prepare AnnotationRegistry
		 */
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRoute.php');
		AnnotationRegistry::registerFile(__DIR__ . '/../ApiRouteSpec.php');

		AnnotationReader::addGlobalIgnoredName('persistent');
		AnnotationReader::addGlobalIgnoredName('inject');

		foreach ($config->ignoreAnnotation as $ignore) {
			AnnotationReader::addGlobalIgnoredName($ignore);
		}
	}

	/**
	 * @param array<mixed> $compilerConfig
	 */
	private function setupReader(array $compilerConfig): void
	{
		$cachePath = $compilerConfig['parameters']['tempDir'] . '/cache/ApiRouter.Annotations';

		/**
		 * Prepare AnnotationReader - use cached values
		 */
		$this->reader = new CachedReader(
			new AnnotationReader(),
			new FilesystemCache($cachePath),
			$compilerConfig['parameters']['debugMode']
		);
	}

	/**
	 * @return array<string, ApiRoute[]>
	 */
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

	/**
	 * @param array<ApiRoute> $routes
	 */
	private function findRoutesInPresenter(string $presenter, array &$routes): void
	{
		$r = new ReflectionClass($presenter);

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

		$route->setDescription(Annotations::getAnnotation($r, 'description'));

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

	/**
	 * @param array<ApiRoute> $routes
	 */
	private function findPresenterMethodRoute(
		ReflectionMethod $method_reflection,
		array &$routes,
		ApiRoute $route
	): void
	{
		$actionRoute = $this->reader->getMethodAnnotation($method_reflection, ApiRoute::class);

		/**
		 * Get action without that ^action string
		 */
		$action = lcfirst(preg_replace('/^action/', '', $method_reflection->name));

		/**
		 * Route can be defined also for particular action
		 */
		if (!$actionRoute) {
			$route->setAction($action);

			return;
		}

		$actionRoute->setDescription(Annotations::getAnnotation($method_reflection, 'description'));

		/**
		 * Action route will inherit presenter name, priority, etc from parent route
		 */
		$actionRoute->setPresenter($actionRoute->getPresenter() ?: $route->getPresenter());
		$actionRoute->setPriority($actionRoute->getPriority() ?: $route->getPriority());
		$actionRoute->setFormat($actionRoute->getFormat() ?: $route->getFormat());
		$actionRoute->setSection($actionRoute->getSection() ?: $route->getSection());
		$actionRoute->setAction($action, $actionRoute->getMethod() ?: null);

		$routes[$route->getPriority()][] = $actionRoute;
	}

	/**
	 * @param array<ApiRoute> $routes
	 * @return array<ApiRoute>
	 */
	private function sortByPriority(array $routes): array
	{
		$return = [];

		foreach ($routes as $priority_routes) {
			foreach ($priority_routes as $route) {
				$return[] = $route;
			}
		}

		return $return;
	}

}
