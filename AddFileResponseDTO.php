<?php declare(strict_types=1);

namespace Apiv2o7Module\Files;

/**
 * @OA\Schema()
 */
class AddFileResponseDTO
{
	/**
	 * @var string
	 * @OA\Property()
	 **/
	public $filepath;

	/**
	 * @var bool
	 * @OA\Property()
	 **/
	public $ok;

	public function __construct(string $filepath, bool $ok)
	{
		$this->filepath = $filepath;
		$this->ok = $ok;
	}

	public function getFilepath(): string
	{
		return $this->filepath;
	}

	public function getOk(): bool
	{
		return $this->ok;
	}

}
