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
     * @Enum({"CREATE", "READ", "UPDATE", "DELETE"})
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
	 * @var array
	 */
	protected $example = [];


	/**
	 * @param array $data
	 */
	public function __construct(array $data)
	{
		foreach ($data as $key => $value) {
			$method = 'set' . ucfirst($key);

			if (!method_exists($this, $method)) {
				throw new ApiRouteWrongPropertyException(
					sprintf('Unknown property "%s" on annotation "%s"', $key, get_class($this))
				);
			}

			$this->$method($value);
		}
	}


	public function setDescription($description)
	{
		$this->description = $description;
	}


	public function getDescription()
	{
		return $this->description;
	}


	protected function setPath($path)
	{
		if (!$path) {
			throw new ApiRouteWrongPropertyException('ApiRoute path can not be empty');
		}

		$this->path = (string) $path;
	}


	public function getPath()
	{
		return $this->path;
	}


	protected function setMethod($method)
	{
		$this->method = strtoupper($method);
	}


	public function getMethod()
	{
		return $this->method;
	}


	protected function setParameters(array $parameters)
	{
		foreach ($parameters as $key => $info) {
			if ('/<(\w+)>/' === strpos($this->getPath(), "<{$key}>")) {
				throw new ApiRouteWrongPropertyException("Parameter $key is not present in url mask");
			}

			foreach ($info as $info_key => $value) {
				if (!in_array($info_key, $this->parameters_infos)) {
					throw new ApiRouteWrongPropertyException(sprintf(
						"You cat set only these description informations: [%s] - \"%s\" given",
						implode(', ', $this->parameters_infos),
						$info_key
					));
				}

				if (!is_scalar($value)) {
					throw new ApiRouteWrongPropertyException(
						"You cat set only scalar parameters informations"
					);
				}
			}
		}

		$this->parameters = $parameters;
	}


	public function getParameters()
	{
		return $this->parameters;
	}


	public function setPriority($priority)
	{
		$this->priority = $priority;
	}


	public function getPriority()
	{
		return $this->priority;
	}


	public function setFormat($format)
	{
		$this->format = $format;
	}


	public function getFormat()
	{
		return $this->format;
	}


	public function setExample($example)
	{
		$this->example = $example;
	}


	public function getExample()
	{
		return $this->example;
	}

}

