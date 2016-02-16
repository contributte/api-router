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
		'POST'   => FALSE,
		'GET'    => FALSE,
		'PUT'    => FALSE,
		'DELETE' => FALSE
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
	 * @param mixed $data
	 */
	public function __construct($path, $presenter = NULL, array $data = [])
	{
		if (!is_array($path)) {
			$data['path'] = $path;
			$data['presenter'] = $presenter;
		} else {
			$data = $path;
		}

		if (!empty($data['value'])) {
			$this->setPath($data['value']);

			unset($data['value']);
		}

		parent::__construct($data);
	}


	public function setPresenter($presenter)
	{
		$this->presenter = $presenter;
	}


	public function getPresenter()
	{
		return $this->presenter;
	}


	public function setAction($method, $action) {
		$this->actions[$method] = $action;
	}


	private function setDefaults(array $defaults)
	{
		$this->defaults = $defaults;
	}


	private function getRequirement($p)
	{
		return isset($this->requirements[$p]) ? $this->requirements[$p] : '[\s]+';
	}


	public function prepareForMatch($string)
	{
		return sprintf('/%s/', str_replace('/', '\/', $string));
	}


	protected function resolveMethod(Nette\Http\IRequest $request) {
		if (!empty($request->getHeader('X-HTTP-Method-Override'))) {
			return Strings::upper($request->getHeader('X-HTTP-Method-Override'));
		}

		return Strings::upper($request->getMethod());
	}


	public function getPlacehodlerParameters()
	{
		if ($this->placeholder_order) {
			return array_filter($this->placeholder_order);
		}

		$return = [];

		$mask = preg_replace_callback('/(<(\w+)>)|\[|\]/', function($item) use (&$return) {
			$return[] = $placeholder;
		}, $this->path);

		return $return;
	}


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


	public function toArray()
	{
		return [
			'@ApiRoute' => [
				'format' => $this->getFormat(),
				'path' => $this->getPath(),
				'description' => $this->getDescription(),
				'methods' => array_keys(array_filter($this->actions)),
				'parameters' => $this->getParameters()
			]
		];
	}


	public function getFormatFull()
	{
		return $this->formats[$this->getFormat()];
	}

	public function getMethods()
	{
		return array_keys(array_filter($this->actions));
	}


	/********************************************************************************
	 *                              Interface IRouter                               *
	 ********************************************************************************/


	/**
	 * Maps HTTP request to a Request object.
	 * @return Request|NULL
	 */
	function match(Nette\Http\IRequest $httpRequest)
	{
		$url = $httpRequest->getUrl();

		$path = '/' . str_replace($url->getBasePath(), '', $url->getPath());

		/**
		 * Build path mask
		 */
		$order = &$this->placeholder_order;
		$parameters = $this->parameters;

		$mask = preg_replace_callback('/(<(\w+)>)|\[|\]/', function($item) use (&$order, $parameters) {
			if ($item[0] == '[' || $item[0] == ']') {
				$order[] = NULL;

				return $item[0];
			}

			list(, , $placeholder) = $item;

			$parameter = isset($parameters[$placeholder]) ? $parameters[$placeholder] : [];

			$regex = isset($parameter['requirement']) ? $parameter['requirement'] : '\w+';
			$has_default = array_key_exists('default', $parameter);
			
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
		$action = $this->actions[$this->resolveMethod($httpRequest)];

		if (!$action) {
			return NULL;
		}

		/**
		 * Basic params
		 */
		$params = $httpRequest->getQuery();
		$params['action'] = $action;

		/**
		 * Route mask parameters
		 */
		array_shift($matches);

		foreach ($this->placeholder_order as $key => $name) {
			if (NULL !== $name) {
				$params[$name] = reset($matches[$key]) ?: NULL;
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
	function constructUrl(Request $request, Nette\Http\Url $url)
	{

	}

}
