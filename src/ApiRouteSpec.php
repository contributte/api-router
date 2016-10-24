<?php

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter;

use Nette;
use Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException;

abstract class ApiRouteSpec extends Nette\Object
{

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var string
	 */
	protected $path = '/';

	/**
	 * @Enum({"CREATE", "READ", "UPDATE", "DELETE", "OPTIONS"})
	 * @var string
	 */
	protected $method;

	/**
	 * @var array
	 */
	protected $parameters = [];

	/**
	 * @var array
	 */
	protected $parameters_infos = ['requirement', 'type', 'description', 'default'];

	/**
	 * @var int
	 */
	protected $priority = 0;

	/**
	 * @Enum({"json", "xml"})
	 * @var string
	 */
	protected $format;

	/**
	 * @var mixed
	 */
	protected $example;

	/**
	 * @var string
	 */
	protected $section;

	/**
	 * @var array
	 */
	protected $tags = [];

	/**
	 * @var array
	 */
	protected $response_codes = [];

	/**
	 * @Enum({TRUE, FALSE})
	 * @var bool
	 */
	protected $disable = FALSE;


	/**
	 * @param array $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$method = 'set' . str_replace('_', '', ucwords($key, '_'));

			if (!method_exists($this, $method)) {
				throw new ApiRouteWrongPropertyException(
					sprintf('Unknown property "%s" on annotation "%s"', $key, get_class($this))
				);
			}

			$this->$method($value);
		}
	}


	/**
	 * @param string $description
	 * @return void
	 */
	public function setDescription($description)
	{
		$this->description = $description;
	}


	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->description;
	}


	/**
	 * @param string $path
	 * @return void
	 */
	protected function setPath($path)
	{
		if (!$path) {
			throw new ApiRouteWrongPropertyException('ApiRoute path can not be empty');
		}

		$this->path = (string) $path;
	}


	/**
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}


	/**
	 * @param string $method
	 * @return void
	 */
	protected function setMethod($method)
	{
		$this->method = strtoupper($method);
	}


	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}


	/**
	 * @param array $parameters
	 */
	protected function setParameters(array $parameters)
	{
		foreach ($parameters as $key => $info) {
			if (FALSE === strpos($this->getPath(), "<{$key}>")) {
				throw new ApiRouteWrongPropertyException("Parameter <$key> is not present in the url mask");
			}

			foreach ($info as $info_key => $value) {
				if (!in_array($info_key, $this->parameters_infos)) {
					throw new ApiRouteWrongPropertyException(sprintf(
						"You cat set only these description informations: [%s] - \"%s\" given",
						implode(', ', $this->parameters_infos),
						$info_key
					));
				}

				if (!is_scalar($value) && !is_null($value)) {
					throw new ApiRouteWrongPropertyException(
						"You cat set only scalar parameters informations (key [{$info_key}])"
					);
				}
			}
		}

		$this->parameters = $parameters;
	}


	/**
	 * @return array
	 */
	public function getParameters()
	{
		return $this->parameters;
	}


	/**
	 * @param int $priority
	 * @return void
	 */
	public function setPriority($priority)
	{
		$this->priority = $priority;
	}


	/**
	 * @return int
	 */
	public function getPriority()
	{
		return $this->priority;
	}


	/**
	 * @param string $format
	 * @return void
	 */
	public function setFormat($format)
	{
		$this->format = $format;
	}


	/**
	 * @return string
	 */
	public function getFormat()
	{
		return $this->format;
	}


	/**
	 * @param mixed $example
	 * @return void
	 */
	public function setExample($example)
	{
		$this->example = $example;
	}


	/**
	 * @return mixed
	 */
	public function getExample()
	{
		return $this->example;
	}


	/**
	 * @param string $section
	 * @return void
	 */
	public function setSection($section)
	{
		$this->section = $section;
	}


	/**
	 * @return string
	 */
	public function getSection()
	{
		return $this->section;
	}


	/**
	 * @param array $tags
	 * @return void
	 */
	public function setTags(array $tags)
	{
		$this->tags = $tags;
	}


	/**
	 * @return array
	 */
	public function getTags()
	{
		$return = [];

		/**
		 * Tag may be saves aither with color: [tagName => color] or without: [tagName]
		 */
		foreach ($this->tags as $tag => $color) {
			if (is_numeric($tag)) {
				$return[$color] = '#9b59b6';
			} else {
				$return[$tag] = $color;
			}
		}

		return $return;
	}


	/**
	 * @param array $response_codes
	 * @return void
	 */
	public function setResponseCodes(array $response_codes)
	{
		$this->response_codes = $response_codes;
	}


	/**
	 * @return array
	 */
	public function getResponseCodes()
	{
		return $this->response_codes;
	}


	/**
	 * @param bool $disable
	 */
	public function setDisable($disable)
	{
		$this->disable = (bool) $disable;
	}


	/**
	 * @return bool
	 */
	public function getDisable()
	{
		return $this->disable;
	}

}

