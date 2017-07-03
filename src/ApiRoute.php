<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter;

use Nette\Application\IRouter;
use Nette\Application\Request;
use Nette;
use Nette\Utils\Strings;

/**
 * @method mixed onMatch(static, Nette\Application\Request $request)
 * 
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class ApiRoute extends ApiRouteSpec implements IRouter
{

	/**
	 * @var callable[]
	 */
	public $onMatch;

	/**
	 * @var string
	 */
	private $presenter;

	/**
	 * @var array
	 */
	private $actions = [
		'POST'    => FALSE,
		'GET'     => FALSE,
		'PUT'     => FALSE,
		'DELETE'  => FALSE,
		'OPTIONS' => FALSE,
		'PATCH'   => FALSE
	];

	/**
	 * @var array
	 */
	private $default_actions = [
		'POST'    => 'create',
		'GET'     => 'read',
		'PUT'     => 'update',
		'DELETE'  => 'delete',
		'OPTIONS' => 'options',
		'PATCH'   => 'patch'
	];

	/**
	 * @var array
	 */
	private $formats = [
		'json' => 'application/json',
		'xml'  => 'application/xml'
	];

	/**
	 * @var array
	 */
	private $placeholder_order = [];


	/**
	 * @param mixed  $data
	 * @param string $presenter
	 * @param array  $data
	 */
	public function __construct($path, $presenter = NULL, array $data = [])
	{
		/**
		 * Interface for setting route via annotation or directly
		 */
		if (!is_array($path)) {
			$data['value'] = $path;
			$data['presenter'] = $presenter;

			if (empty($data['methods'])) {
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


	/**
	 * @param string $presenter
	 * @return void
	 */
	public function setPresenter($presenter)
	{
		$this->presenter = $presenter;
	}


	/**
	 * @return string
	 */
	public function getPresenter()
	{
		return $this->presenter;
	}


	/**
	 * @param string $action
	 * @param string $method
	 * @return void
	 */
	public function setAction($action, $method = NULL) {
		if (is_null($method)) {
			$method = array_search($action, $this->default_actions);
		}

		if (!isset($this->default_actions[$method])) {
			return;
		}

		$this->actions[$method] = $action;
	}


	/**
	 * @param  string $string
	 * @return string
	 */
	private function prepareForMatch($string)
	{
		return sprintf('/%s/', str_replace('/', '\/', $string));
	}


	/**
	 * Get all parameters from url mask
	 * @return array
	 */
	public function getPlacehodlerParameters()
	{
		if (!empty($this->placeholder_order)) {
			return array_filter($this->placeholder_order);
		}

		$return = [];

		preg_replace_callback('/<(\w+)>/', function($item) use (&$return) {
			$return[] = end($item);
		}, $this->path);

		return $return;
	}


	/**
	 * Get required parameters from url mask
	 * @return array
	 */
	public function getRequiredParams()
	{
		$regex = '/\[[^\[]+?\]/';
		$path = $this->getPath();

		while (preg_match($regex, $path)) {
			$path = preg_replace($regex, '', $path);
		}

		$required = [];

		preg_replace_callback('/<(\w+)>/', function($item) use (&$required) {
			$required[] = end($item);
		}, $path);

		return $required;
	}


	/**
	 * @param  Nette\Http\IRequest $httpRequest
	 * @return void
	 */
	public function resolveFormat(Nette\Http\IRequest $httpRequest)
	{
		if ($this->getFormat()) {
			return;
		}

		$header = $httpRequest->getHeader('Accept');

		foreach ($this->formats as $format => $format_full) {
			$format_full = Strings::replace($format_full, '/\//', '\/');

			if (Strings::match($header, "/{$format_full}/")) {
				$this->setFormat($format);
			}
		}

		$this->setFormat('json');
	}


	/**
	 * @return string
	 */
	public function getFormatFull()
	{
		return $this->formats[$this->getFormat()];
	}


	/**
	 * @param array $methods
	 */
	public function setMethods(array $methods)
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


	/**
	 * @return array
	 */
	public function getMethods()
	{
		return array_keys(array_filter($this->actions));
	}


	/**
	 * @param  Nette\Http\IRequest $request
	 * @return string
	 */
	public function resolveMethod(Nette\Http\IRequest $request) {
		if (!empty($request->getHeader('X-HTTP-Method-Override'))) {
			return Strings::upper($request->getHeader('X-HTTP-Method-Override'));
		}

		if ($method = Strings::upper($request->getQuery('__apiRouteMethod'))) {
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
	 * Maps HTTP request to a Request object.
	 * @return Request|NULL
	 */
	public function match(Nette\Http\IRequest $httpRequest)
	{
		/**
		 * ApiRoute can be easily disabled
		 */
		if ($this->disable) {
			return NULL;
		}

		$url = $httpRequest->getUrl();

		$path = $url->getPath();

		/**
		 * Build path mask
		 */
		$order = &$this->placeholder_order;
		$parameters = $this->parameters;

		$mask = preg_replace_callback('/(<(\w+)>)|\[|\]/', function($item) use (&$order, $parameters) {
			if ($item[0] == '[' || $item[0] == ']') {
				if ($item[0] == '[') {
					$order[] = NULL;
				}

				return $item[0];
			}

			list(,, $placeholder) = $item;

			$parameter = isset($parameters[$placeholder]) ? $parameters[$placeholder] : [];

			$regex = isset($parameter['requirement']) ? $parameter['requirement'] : '\w+';
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
			return NULL;
		}

		/**
		 * Did some action to the request method exists?
		 */
		$this->resolveFormat($httpRequest);
		$method = $this->resolveMethod($httpRequest);
		$action = $this->actions[$method];

		if (!$action) {
			return NULL;
		}

		/**
		 * Basic params
		 */
		$params = $httpRequest->getQuery();
		$params['action'] = $action;
		$required_params = $this->getRequiredParams();

		/**
		 * Route mask parameters
		 */
		array_shift($matches);

		foreach ($this->placeholder_order as $key => $name) {
			if (NULL !== $name && isset($matches[$key])) {
				$params[$name] = reset($matches[$key]) ?: NULL;

				/**
				 * Required parameters
				 */
				if (empty($params[$name]) && in_array($name, $required_params)) {
					return NULL;
				}
			}
		}

		$request = new Request(
			$this->presenter,
			$method,
			$params,
			$httpRequest->getPost(),
			$httpRequest->getFiles(),
			[Request::SECURED => $httpRequest->isSecured()]
		);

		/**
		 * Trigger event - route matches
		 */
		$this->onMatch($this, $request);

		return $request;
	}


	/**
	 * Constructs absolute URL from Request object.
	 * @return string|NULL
	 */
	public function constructUrl(Request $request, Nette\Http\Url $url)
	{
		if ($this->presenter != $request->getPresenterName()) {
			return NULL;
		}

		$base_url = $url->getBaseUrl();

		$action = $request->getParameter('action');
		$parameters = $request->getParameters();
		unset($parameters['action']);
		$path = ltrim($this->getPath(), '/');

		if (FALSE === array_search($action, $this->actions)) {
			return NULL;
		}

		foreach ($parameters as $name => $value) {
			if (strpos($path, "<{$name}>") !== FALSE && $value !== NULL) {
				$path = str_replace("<{$name}>", $value, $path);

				unset($parameters[$name]);
			}
		}

		$path = preg_replace_callback('/\[.+?\]/', function($item) {
			if (strpos(end($item), '<')) {
				return '';
			}

			return end($item);
		}, $path);

		/**
		 * There are still some required parameters in url mask
		 */
		if (preg_match('/<\w+>/', $path)) {
			return NULL;
		}

		$path = str_replace(['[', ']'], '', $path);

		$query = http_build_query($parameters);

		return $base_url . $path . ($query ? '?' . $query : '');
	}

}
