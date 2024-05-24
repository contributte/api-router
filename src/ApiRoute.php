<?php declare(strict_types = 1);

namespace Contributte\ApiRouter;

use Nette\Http\IRequest;
use Nette\Http\UrlScript;
use Nette\Routing\Router;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class ApiRoute extends ApiRouteSpec implements Router
{

	use SmartObject;

	/** @var callable[] */
	public array $onMatch = [];

	private ?string $presenter = null;

	/** @var array<string, bool> */
	private array $actions = [
		'POST' => false,
		'GET' => false,
		'PUT' => false,
		'DELETE' => false,
		'OPTIONS' => false,
		'PATCH' => false,
		'HEAD' => false,
	];

	/** @var array<string, string> */
	private array $defaultActions = [
		'POST' => 'create',
		'GET' => 'read',
		'PUT' => 'update',
		'DELETE' => 'delete',
		'OPTIONS' => 'options',
		'PATCH' => 'patch',
		'HEAD' => 'head',
	];

	/** @var array<string, string> */
	private array $formats = [
		'json' => 'application/json',
		'xml' => 'application/xml',
	];

	/** @var array<mixed> */
	private array $placeholderOrder = [];

	private bool $autoBasePath = true;

	/**
	 * @param array<mixed> $data
	 */
	public function __construct(mixed $path, ?string $presenter = null, array $data = [])
	{
		/**
		 * Interface for setting route via annotation or directly
		 */
		if (!is_array($path)) {
			$data['value'] = $path;
			$data['presenter'] = $presenter;

			if (!isset($data['methods']) || !$data['methods']) {
				$this->actions = $this->defaultActions;
			} else {
				foreach ($data['methods'] as $method => $action) {
					if (is_string($method)) {
						$this->setAction($action, $method);
					} else {
						$m = $action;

						if (isset($this->defaultActions[$m])) {
							$this->setAction($this->defaultActions[$m], $m);
						}
					}
				}

				unset($data['methods']);
			}
		} else {
			$data = $path;
		}

		/**
		 * Set Path
		 */
		$this->setPath($data['value']);
		unset($data['value']);

		parent::__construct($data);
	}

	/**
	 * @phpstan-param array{
	 *     path: string,
	 *     presenter: string|null,
	 *     parameters: array<mixed>,
	 *     actions: array<string, string>,
	 *     formats: array<string, string>,
	 *     placeholderOrder: array<mixed>,
	 *     disable: bool,
	 *     autoBasePath: bool
	 * } $data
	 */
	public static function fromArray(array $data): self
	{
		$route = new self($data['path'], $data['presenter'], []);
		$route->parameters = $data['parameters'];
		$route->actions = $data['actions'];
		$route->formats = $data['formats'];
		$route->placeholderOrder = $data['placeholderOrder'];
		$route->disable = $data['disable'];
		$route->autoBasePath = $data['autoBasePath'];

		return $route;
	}

	public function getMask(): string
	{
		return $this->path;
	}

	public function setPresenter(?string $presenter): void
	{
		$this->presenter = $presenter;
	}

	public function getPresenter(): ?string
	{
		return $this->presenter;
	}

	public function setAction(string $action, ?string $method = null): void
	{
		if ($method === null) {
			$method = array_search($action, $this->defaultActions, true);
		}

		if (!isset($this->defaultActions[$method])) {
			return;
		}

		$this->actions[$method] = $action;
	}

	public function getAction(string $method): ?string
	{
		return $this->actions[$method] ?? null;
	}

	/**
	 * Get all parameters from url mask
	 *
	 * @return array<mixed>
	 */
	public function getPlacehodlerParameters(): array
	{
		if ($this->placeholderOrder) {
			return array_filter($this->placeholderOrder);
		}

		$return = [];

		// @phpcs:ignore
		preg_replace_callback('/<(\w+)>/', function ($item) use (&$return): void {
			$return[] = end($item);
		}, $this->path);

		return $return;
	}

	/**
	 * Get required parameters from url mask
	 *
	 * @return array<mixed>
	 */
	public function getRequiredParams(): array
	{
		$regex = '/\[[^\[]+?\]/';
		$path = $this->getPath();

		while (preg_match($regex, $path)) {
			$path = preg_replace($regex, '', $path);
		}

		$required = [];

		// @phpcs:ignore
		preg_replace_callback('/<(\w+)>/', function ($item) use (&$required): void {
			$required[] = end($item);
		}, $path);

		return $required;
	}

	public function resolveFormat(IRequest $httpRequest): void
	{
		if ($this->getFormat()) {
			return;
		}

		$header = $httpRequest->getHeader('Accept');

		foreach ($this->formats as $format => $format_full) {
			$format_full = Strings::replace($format_full, '/\//', '\/');

			if (Strings::match($header, '/' . $format_full . '/')) {
				$this->setFormat($format);
			}
		}

		$this->setFormat('json');
	}

	public function getFormatFull(): string
	{
		return $this->formats[$this->getFormat()];
	}

	/**
	 * @param array<string, string> $methods
	 */
	public function setMethods(array $methods): void
	{
		foreach ($methods as $method => $action) {
			if (is_string($method)) {
				$this->setAction($action, $method);
			} else {
				$m = $action;

				if (isset($this->defaultActions[$m])) {
					$this->setAction($this->defaultActions[$m], $m);
				}
			}
		}
	}

	/**
	 * @return array<string, string>
	 */
	public function getMethods(): array
	{
		return array_keys(array_filter($this->actions));
	}

	public function resolveMethod(IRequest $request): string
	{
		if ($request->getHeader('X-HTTP-Method-Override')) {
			return Strings::upper($request->getHeader('X-HTTP-Method-Override'));
		}

		if ($request->getQuery('__apiRouteMethod')) {
			$method = Strings::upper($request->getQuery('__apiRouteMethod'));

			if (isset($this->actions[$method])) {
				return $method;
			}
		}

		return Strings::upper($request->getMethod());
	}

	public function setAutoBasePath(bool $autoBasePath): void
	{
		$this->autoBasePath = $autoBasePath;
	}

	/********************************************************************************
	 *                              Interface IRouter *
	 ********************************************************************************/

	/**
	 * Maps HTTP request to an array.
	 *
	 * @return array<mixed>|null
	 */
	public function match(IRequest $httpRequest): ?array
	{
		/**
		 * ApiRoute can be easily disabled
		 */
		if ($this->disable) {
			return null;
		}

		$url = $httpRequest->getUrl();

		if ($this->autoBasePath) {
			// Resolve base path
			$basePath = $url->getBasePath();

			if (strncmp($url->getPath(), $basePath, strlen($basePath)) !== 0) {
				return null;
			}

			$path = substr($url->getPath(), strlen($basePath));

			// Ensure start with /
			$path = '/' . ltrim($path, '/');
		} else {
			$path = $url->getPath();
		}

		// Build path mask
		// @phpcs:ignore
		$order = &$this->placeholderOrder;
		$parameters = $this->parameters;

		// @phpcs:ignore
		$mask = preg_replace_callback('/(<(\w+)>)|\[|\]/', function ($item) use (&$order, $parameters) {
			if ($item[0] === '[' || $item[0] === ']') {
				if ($item[0] === '[') {
					$order[] = null;
				}

				return $item[0];
			}

			[, , $placeholder] = $item;

			$parameter = $parameters[$placeholder] ?? [];

			$regex = $parameter['requirement'] ?? '\w+';
			$has_default = array_key_exists('default', $parameter);
			$regex = preg_replace('~\(~', '(?:', $regex);

			if ($has_default) {
				$order[] = $placeholder;

				return sprintf('(%s)?', $regex);
			}

			$order[] = $placeholder;

			return sprintf('(%s)', $regex);
		}, $this->path);

		$mask = '^' . str_replace(['[', ']'], ['(', ')?'], $mask) . '$';

		/**
		 * Prepare paths for regex match (escape slashes)
		 */
		if (!preg_match_all($this->prepareForMatch($mask), $path, $matches)) {
			return null;
		}

		/**
		 * Did some action to the request method exists?
		 */
		$this->resolveFormat($httpRequest);
		$method = $this->resolveMethod($httpRequest);
		$action = $this->actions[$method] ?? null;

		if (!$action) {
			return null;
		}

		/**
		 * Basic params
		 */
		$params = $httpRequest->getQuery();
		$required_params = $this->getRequiredParams();

		/**
		 * Route mask parameters
		 */
		array_shift($matches);

		foreach ($this->placeholderOrder as $key => $name) {
			if ($name !== null && isset($matches[$key])) {
				$params[$name] = reset($matches[$key]) ?: null;

				/**
				 * Required parameters
				 */
				if (!$params[$name] && in_array($name, $required_params, true)) {
					return null;
				}
			}
		}

		$xs = array_merge([
			'presenter' => $this->presenter,
			'action' => $action,
			'method' => $method,
			'post' => $httpRequest->getPost(),
			'files' => $httpRequest->getFiles(),
		], $params);

		/**
		 * Trigger event - route matches
		 */
		$this->onMatch($this, $xs);

		return $xs;
	}

	/**
	 * Constructs absolute URL from array.
	 *
	 * @param array<mixed> $params
	 */
	public function constructUrl(array $params, UrlScript $url): ?string
	{
		if ($this->presenter !== $params['presenter']) {
			return null;
		}

		$base_url = $url->getBaseUrl();

		$action = $params['action'];
		unset($params['presenter']);
		unset($params['action']);
		$parameters = $params;
		$path = ltrim($this->getPath(), '/');

		if (array_search($action, $this->actions, true) === false) {
			return null;
		}

		foreach ($parameters as $name => $value) {
			if (strpos($path, '<' . $name . '>') !== false && $value !== null) {
				$path = str_replace('<' . $name . '>', (string) $value, $path);

				unset($parameters[$name]);
			}
		}

		$path = preg_replace_callback('/\[.+?\]/', function ($item) {
			if (strpos(end($item), '<')) {
				return '';
			}

			return end($item);
		}, $path);

		/**
		 * There are still some required parameters in url mask
		 */
		if (preg_match('/<\w+>/', $path)) {
			return null;
		}

		$path = str_replace(['[', ']'], '', $path);

		$query = http_build_query($parameters);

		return $base_url . $path . ($query ? '?' . $query : '');
	}

	/**
	 * @return array{
	 *     path: string,
	 *     presenter: string|null,
	 *     parameters: array<mixed>,
	 *     actions: array<string, string>,
	 *     formats: array<string, string>,
	 *     placeholderOrder: array<mixed>,
	 *     disable: bool,
	 *     autoBasePath: bool
	 * }
	 */
	public function toArray(): array
	{
		return [
			'path' => $this->path,
			'presenter' => $this->presenter,
			'parameters' => $this->parameters,
			'actions' => $this->actions,
			'formats' => $this->formats,
			'placeholderOrder' => $this->placeholderOrder,
			'disable' => $this->disable,
			'autoBasePath' => $this->autoBasePath,
		];
	}

	private function prepareForMatch(string $string): string
	{
		return sprintf('/%s/', str_replace('/', '\/', $string));
	}

}
