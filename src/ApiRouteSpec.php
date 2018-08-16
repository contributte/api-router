<?php

declare(strict_types=1);

/**
 * @copyright   Copyright (c) 2016 ublaboo <ublaboo@paveljanda.com>
 * @author      Pavel Janda <me@paveljanda.com>
 * @package     Ublaboo
 */

namespace Ublaboo\ApiRouter;

use Doctrine\Common\Annotations\Annotation\Enum;
use Ublaboo\ApiRouter\Exception\ApiRouteWrongPropertyException;
use Nette;

abstract class ApiRouteSpec
{
	use Nette\SmartObject;
	
	/**
	 * @var string|null
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
	protected $format = 'json';

	/**
	 * @var array|null
	 */
	protected $example;

	/**
	 * @var string|null
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
	 * @Enum({true, false})
	 * @var bool
	 */
	protected $disable = false;


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


	public function setDescription(?string $description): void
	{
		$this->description = $description;
	}


	public function getDescription(): ?string
	{
		return $this->description;
	}


	protected function setPath(string $path): void
	{
		if (!$path) {
			throw new ApiRouteWrongPropertyException('ApiRoute path can not be empty');
		}

		$this->path = (string) $path;
	}


	public function getPath(): string
	{
		return $this->path;
	}


	protected function setMethod(string $method): void
	{
		$this->method = strtoupper($method);
	}


	public function getMethod(): string
	{
		return $this->method;
	}


	/**
	 * @throws ApiRouteWrongPropertyException
	 */
	protected function setParameters(array $parameters): void
	{
		foreach ($parameters as $key => $info) {
			if (strpos($this->getPath(), "<{$key}>") === false) {
				throw new ApiRouteWrongPropertyException("Parameter <$key> is not present in the url mask");
			}

			foreach ($info as $info_key => $value) {
				if (!in_array($info_key, $this->parameters_infos, true)) {
					throw new ApiRouteWrongPropertyException(sprintf(
						'You cat set only these description informations: [%s] - "%s" given',
						implode(', ', $this->parameters_infos),
						$info_key
					));
				}

				if (!is_scalar($value) && $value !== null) {
					throw new ApiRouteWrongPropertyException(
						"You cat set only scalar parameters informations (key [{$info_key}])"
					);
				}
			}
		}

		$this->parameters = $parameters;
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	public function setPriority(int $priority): void
	{
		$this->priority = $priority;
	}


	public function getPriority(): int
	{
		return $this->priority;
	}


	public function setFormat(string $format): void
	{
		$this->format = $format;
	}


	public function getFormat(): string
	{
		return $this->format;
	}


	public function setExample(?array $example): void
	{
		$this->example = $example;
	}


	public function getExample(): ?array
	{
		return $this->example;
	}


	public function setSection(?string $section): void
	{
		$this->section = $section;
	}


	public function getSection(): ?string
	{
		return $this->section;
	}


	public function setTags(array $tags): void
	{
		$this->tags = $tags;
	}


	public function getTags(): array
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


	public function setResponseCodes(array $response_codes): void
	{
		$this->response_codes = $response_codes;
	}


	public function getResponseCodes(): array
	{
		return $this->response_codes;
	}


	public function setDisable(bool $disable): void
	{
		$this->disable = (bool) $disable;
	}


	public function getDisable(): bool
	{
		return $this->disable;
	}
}
