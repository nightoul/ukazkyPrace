<?php declare(strict_types=1);

namespace Apiv2o7Module\Files;

/**
 * @OA\Schema(required={"filename", "file"})
 */
class AddFileRequestDTO
{
	/**
	 * @var string
	 * @OA\Property(example="example_filename.pdf")
	 */
	public $filename;

	/**
	 * @var string
	 * @OA\Property(format="base64", example="base64")
	 */
	public $file;

	/**
	 * @var string
	 * @OA\Property(example="sometoken")
	 */
	public $token;

	/**
	 * @var int
	 * @OA\Property(example=1234)
	 */
	public $adminUserId;

	/**
	 * @var string
	 * @OA\Property(example="images")
	 */
	public $directory;

	/**
	 * @var bool
	 * @OA\Property()
	 **/
	public $isSignature;


	public function getFilename(): string
	{
		return $this->filename;
	}

	public function getFile(): string
	{
		return $this->file;
	}

	public function getToken(): string
	{
		return $this->token;
	}

	public function getAdminUserId(): int
	{
		return $this->adminUserId;
	}

	public function getDirectory(): string
	{
		return $this->directory;
	}

	public function getIsSignature(): bool
	{
		return $this->isSignature;
	}

}
