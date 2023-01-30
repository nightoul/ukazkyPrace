<?php declare(strict_types=1);

namespace Apiv2o7Module;

use Apiv2o7Module\Files\AddFileInvestorRequestDTO;
use Apiv2o7Module\Files\AddFileInvestorResponseDTO;
use Apiv2o7Module\Files\AddFileRequestDTO;
use Apiv2o7Module\Files\AddFileResponseDTO;
use Erp\Model\AdminUsers\AdminUsersRepository;
use Erp\Model\Files\FilesRepository;
use Erp\Model\Files\FilesService;
use Erp\Model\Images\ImagesService;
use Exception;
use Nette\Database\Explorer;
use Nette\DI\Attributes\Inject;

class FilesPresenter extends BasePresenter
{
	#[Inject]
	public AdminUsersRepository $adminUsersRepo;

	#[Inject]
	public FilesRepository $filesRepo;

	#[Inject]
	public Explorer $database;

	protected bool $checkToken = FALSE;


	/**
	 * @OA\Post(
	 *     path="/api_v2.7/files/add-file",
	 *     tags={"Files"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(ref="#/components/schemas/AddFileRequestDTO")
	 *     ),
	 *     @OA\Response(
	 *         response="200",
	 *         description="Files added",
	 *         @OA\JsonContent(ref="#/components/schemas/AddFileResponseDTO")
	 *     ),
	 *     @ OA\Response(
	 *         response="400",
	 *         description="Bad request",
	 *     )
	 * )
	 */
	public function actionAddFile()
	{
		$this->enableCORS();
		$this->checkMethodType('POST');

		/** @var AddFileRequestDTO $request */
		$request = $this->deserializeRequest(AddFileRequestDTO::class);

		if ($this->validateAdminUserToken($request->getToken(), $request->getAdminUserId())) {
			try {

				$fileStream = base64_decode($request->getFile());
				$processedFileName = substr(md5('a' . random_int(0, mt_getrandmax())), 0, 7) . '_' . $request->getFilename();

				if (!$request->getIsSignature()) {
					if ($request->getDirectory() === 'files/files') {
						$filepath = '/' . $request->getDirectory() . '/' . FilesService::getFileDir($processedFileName) . '/' . $processedFileName;
					} elseif ($request->getDirectory() === 'files/images') {
						$filepath = '/' . $request->getDirectory() . '/' . ImagesService::getImageDir($processedFileName) . '/' . $processedFileName;
					} elseif ($request->getDirectory() === 'media') {
						$filepath = '/' . $request->getDirectory() . '/' . FilesService::getMediaFileDir($request->getFilename()) . '/' . $request->getFilename();
					} elseif ($request->getDirectory() === '') {
						$filepath = '/files/files/' . $processedFileName;
					}
				} else {
					$filepath = '/' . $request->getDirectory() . '/' . $processedFileName;
				}

				file_put_contents(WWW_DIR . $filepath, $fileStream);

				$this->formatObjectPayload(new AddFileResponseDTO($filepath, true));

			} catch (Exception $e) {
				$this->formatObjectPayload(new AddFileResponseDTO($e->getMessage(), false));
			}
		} else {
			$this->formatObjectPayload(new AddFileResponseDTO('error: token validation failed', false));
		}
	}

	private function validateAdminUserToken($token, $adminUserId): bool
	{
		return $this->database->table('admin_users_tokens')->where(['token' => $token, 'admin_user_id' => $adminUserId])->count() > 0;
	}
}

