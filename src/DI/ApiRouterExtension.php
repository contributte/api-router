<?php declare(strict_types = 1);

namespace Contributte\ApiRouter\DI;

use Contributte\ApiRouter\ApiRoute;
use Contributte\Utils\Annotations;
use Nette\Application\IPresenter;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\PhpGenerator\ClassType as GClassType;
use ReflectionClass;
use ReflectionMethod;

class ApiRouterExtension extends CompilerExtension
{

	private ?Definition $definition = null;

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$routes = $this->findRoutes($builder);
		$routesStatements = [];

		foreach ($routes as $route) {
			$routesStatements[] = new Statement(ApiRoute::class . '::fromArray', [$route->toArray()]);
		}

		$this->definition = $builder->addDefinition($this->prefix('resolver'))
			->setType(ApiRoutesResolver::class)
			->addSetup('prepandRoutes', [$builder->getDefinition('router'), $routesStatements]);
	}

	public function afterCompile(GClassType $class): void
	{
		parent::afterCompile($class);

		$class->getMethod('initialize')->addBody('$this->getService(?);', [$this->definition->getName()]);
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

		$attributes = $r->getAttributes(ApiRoute::class);

		if ($attributes === []) {
			return;
		}

		$route = $attributes[0]->newInstance();

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
		$attributes = $method_reflection->getAttributes(ApiRoute::class);
		$actionRoute = $attributes !== [] ? $attributes[0]->newInstance() : null;

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
