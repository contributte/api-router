<?php

declare(strict_types=1);

namespace Contributte\ApiRouter;

use Nette;
use Nette\Application\Request;
use Nette\Routing\Router;
use Nette\SmartObject;
use Nette\Utils\Strings;

/**
 * @method mixed onMatch(static, Nette\Application\Request $request)
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class ApiRoute extends ApiRouteSpec implements Router
{

	use SmartObject;

	/**
	 * @var callable[]
	 */
	public $onMatch;

	/**
	 * @var string|null
	 */
	private $presenter;

	/**
	 * @var array
	 */
	private $actions = [
		'POST' => false,
		'GET' => false,
		'PUT' => false,
		'DELETE' => false,
		'OPTIONS' => false,
		'PATCH' => false,
	];

	/**
	 * @var array
	 */
	private $default_actions = [
		'POST' => 'create',
		'GET' => 'read',
		'PUT' => 'update',
		'DELETE' => 'delete',
		'OPTIONS' => 'options',
		'PATCH' => 'patch',
	];

	/**
	 * @var array
	 */
	private $formats = [
		'json' => 'application/json',
		'xml' => 'application/xml',
	];

	/**
	 * @var array
	 */
	private $placeholder_order = [];

	/**
	 * @param mixed $path
	 */
	public function __construct($path, ?string $presenter = null, array $data = [])
	{
		/**
		 * Interface for setting route via annotation or directly
		 */
		if (!is_array($path)) {
			$data['value'] = $path;
			$data['presenter'] = $presenter;

			if (!isset($data['methods']) || !$data['methods']) {
				$this->actions = $this->default_actions;
			} else {
				foreach ($data['methods'] as $method => $action) {
					if (is_string($method)) {
						$this->setAction($action, $method);
					} else {
						$m = $action;

						if (isset($this->default_actions[$m])) {
							$this->setAction($this->default_actions[$m], $m);
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
			$method = array_search($action, $this->default_actions, true);
		}

		if (!isset($this->default_actions[$method])) {
			return;
		}

		$this->actions[$method] = $action;
	}


	/**
	 * Get all parameters from url mask
	 */
	public function getPlacehodlerParameters(): array
	{
		if ($this->placeholder_order) {
			return array_filter($this->placeholder_order);
		}

		$return = [];

		preg_replace_callback('/<(\w+)>/', function ($item) use (&$return): void {
			$return[] = end($item);
		}, $this->path);

		return $return;
	}


	/**
	 * Get required parameters from url mask
	 */
	public function getRequiredParams(): array
	{
		$regex = '/\[[^\[]+?\]/';
		$path = $this->getPath();

		while (preg_match($regex, $path)) {
			$path = preg_replace($regex, '', $path);
		}

		$required = [];

		preg_replace_callback('/<(\w+)>/', function ($item) use (&$required): void {
			$required[] = end($item);
		}, $path);

		return $required;
	}


	public function resolveFormat(Nette\Http\IRequest $httpRequest): void
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


	public function setMethods(array $methods): void
	{
		foreach ($methods as $method => $action) {
			if (is_string($method)) {
				$this->setAction($action, $method);
			} else {
				$m = $action;

				if (isset($this->default_actions[$m])) {
					$this->setAction($this->default_actions[$m], $m);
				}
			}
		}
	}


	public function getMethods(): array
	{
		return array_keys(array_filter($this->actions));
	}


	public function resolveMethod(Nette\Http\IRequest $request): string
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


	/********************************************************************************
	 *                              Interface IRouter                               *
	 ********************************************************************************/

	/**
	 * Maps HTTP request to an array.
	 */
	public function match(Nette\Http\IRequest $httpRequest): ?array
	{
		/**
		 * ApiRoute can be easily disabled
		 */
		if ($this->disable) {
			return null;
		}

		$url = $httpRequest->getUrl();

		// Resolve base path
		$basePath = $url->getBasePath();
		if (strncmp($url->getPath(), $basePath, strlen($basePath)) !== 0) {
			return null;
		}
		$path = substr($url->getPath(), strlen($basePath));

		// Ensure start with /
		$path = '/' . ltrim($path, '/');

		/**
		 * Build path mask
		 */
		$order = &$this->placeholder_order;
		$parameters = $this->parameters;

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

		foreach ($this->placeholder_order as $key => $name) {
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
			Request::SECURED => $httpRequest->isSecured(),
		], $params);

		/**
		 * Trigger event - route matches
		 */
		$this->onMatch($this, $xs);

		return $xs;
	}


	/**
	 * Constructs absolute URL from array.
	 */
	public function constructUrl(array $params, Nette\Http\UrlScript $url): ?string
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


	private function prepareForMatch(string $string): string
	{
		return sprintf('/%s/', str_replace('/', '\/', $string));
	}

}
