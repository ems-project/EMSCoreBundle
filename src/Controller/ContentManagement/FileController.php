<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\File\File;
use EMS\Helpers\Html\Headers;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FileController extends AbstractController
{
    /**
     * @param array<string, mixed> $assetConfig
     */
    public function __construct(
        private readonly FileService $fileService,
        private readonly AssetExtractorService $assetExtractorService,
        private readonly LoggerInterface $logger,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly AssetRuntime $assetRuntime,
        protected array $assetConfig,
        private readonly string $themeColor,
    ) {
    }

    public function getHashAlgo(): JsonResponse
    {
        return new JsonResponse(['hash_algo' => $this->fileService->getAlgo()]);
    }

    public function heads(Request $request): JsonResponse
    {
        $hashes = Json::decode($request->getContent());

        $heads = \iterator_to_array($this->fileService->heads(...$hashes));

        return new JsonResponse($heads);
    }

    public function viewFile(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
    }

    public function downloadFile(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
    }

    public function extractFileContentForced(Request $request, string $sha1): Response
    {
        return $this->extractFileContent($request, $sha1, true);
    }

    public function extractFileContent(Request $request, string $sha1, bool $forced = false): Response
    {
        $this->closeSession($request);
        $filename = $request->get('name', $sha1);

        try {
            $data = $this->assetExtractorService->extractMetaData($sha1, null, $forced, $filename);
        } catch (NotFoundException) {
            throw new NotFoundHttpException(\sprintf('Asset %s not found', $sha1));
        }

        $response = $this->flashMessageLogger->buildJsonResponse([
            'success' => !$data->isEmpty(),
            'content' => $data->getContent(),
            'author' => $data->getAuthor(),
            'date' => $data->getDate(),
            'language' => $data->getLocale(),
            'title' => $data->getTitle(),
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param int $size
     *
     * @deprecated
     */
    public function initUploadFile(?string $sha1, $size, bool $apiRoute, Request $request): Response
    {
        if ($sha1 || $size) {
            @\trigger_error('You should use the routes emsco_file_data_init_upload or emsco_file_api_init_upload which doesn\'t require url parameters', E_USER_DEPRECATED);
        }

        $requestContent = $request->getContent();
        if (!\is_string($requestContent)) {
            throw new \RuntimeException('Unexpected body content');
        }

        $params = Json::decode($requestContent);
        $name = $params['name'] ?? 'upload.bin';
        $type = $params['type'] ?? 'application/bin';
        $hash = $params['hash'] ?? $sha1;
        $size = $params['size'] ?? $size;
        $algo = $params['algo'] ?? 'sha1';

        $user = $this->getUsername();

        if (empty($hash) || empty($algo) || (empty($size) && 0 !== $size)) {
            throw new BadRequestHttpException('Bad Request, invalid json parameters');
        }

        try {
            $uploadedAsset = $this->fileService->initUploadFile($hash, $size, $name, $type, $user, $algo);
        } catch (\Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
        }

        return $this->jsonResponse($uploadedAsset, $apiRoute);
    }

    /** @deprecated */
    public function uploadChunk(?string $sha1, ?string $hash, bool $apiRoute, Request $request): Response
    {
        if (null !== $sha1) {
            $hash = $sha1;
            @\trigger_error('You should use the routes emsco_file_data_chunk_upload or emsco_file_api_chunk_upload which use a hash parameter', E_USER_DEPRECATED);
        }
        if (null === $hash) {
            throw new \RuntimeException('Unexpected null hash');
        }

        $chunk = $request->getContent();

        if (!\is_string($chunk)) {
            throw new \RuntimeException('Unexpected body request');
        }

        $user = $this->getUsername();

        try {
            $uploadedAsset = $this->fileService->addChunk($hash, $chunk, $user);
        } catch (\Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
        }

        return $this->jsonResponse($uploadedAsset, $apiRoute);
    }

    public function indexImages(): Response
    {
        $images = $this->fileService->getImages();
        $response = [];
        foreach ($images as $image) {
            $url = $this->generateUrl('ems_file_view', [
                'sha1' => $image->getSha1(),
                'name' => $image->getName(),
                'type' => $image->getType(),
            ]);
            $response[] = [
                'image' => $url,
                'thumb' => $url,
                'folder' => $image->getUser(),
            ];
        }

        return new JsonResponse($response);
    }

    public function icon(Request $request, int $width, int $height, ?string $background = null): Response
    {
        if ($width !== $height) {
            throw new NotFoundHttpException('File not found');
        }
        $this->closeSession($request);

        if ($width > 128) {
            $config = [
                EmsFields::ASSET_CONFIG_WIDTH => $width,
                EmsFields::ASSET_CONFIG_HEIGHT => $height,
                EmsFields::ASSET_CONFIG_QUALITY => 0,
                EmsFields::ASSET_CONFIG_BACKGROUND => $background ?? "ems-$this->themeColor",
                EmsFields::ASSET_CONFIG_RADIUS => $width / 6,
                EmsFields::ASSET_CONFIG_BORDER_COLOR => '#000000FF',
            ];
        } else {
            $config = [
                EmsFields::ASSET_CONFIG_WIDTH => $width,
                EmsFields::ASSET_CONFIG_HEIGHT => $height,
                EmsFields::ASSET_CONFIG_QUALITY => 0,
                EmsFields::ASSET_CONFIG_COLOR => "ems-$this->themeColor",
            ];
        }
        $image = $this->fileService->generateImage('@EMSCommonBundle/Resources/public/images/ems-logo.png', $config);

        $response = new StreamedResponse(function () use ($image) {
            if ($image->isSeekable() && $image->tell() > 0) {
                $image->rewind();
            }

            while (!$image->eof()) {
                echo $image->read(File::DEFAULT_CHUNK_SIZE);
            }
            $image->close();
        });
        $configObject = $this->fileService->localFileConfig('@EMSCommonBundle/Resources/public/images/ems-logo.png', $config);
        $response->headers->add([
            Headers::CONTENT_DISPOSITION => $configObject->getDisposition().'; '.HeaderUtils::toString(['filename' => 'ems-logo.png'], ';'),
            Headers::CONTENT_TYPE => $configObject->getMimeType(),
        ]);
        $response->setCache([
            'etag' => \hash('sha1', \sprintf('Icon Config: %s', Json::encode($config))),
            'max_age' => 3600,
            's_maxage' => 36000,
            'public' => true,
            'private' => false,
        ]);

        return $response;
    }

    public function uploadFile(Request $request): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('upload');
        $type = $request->get('type', false);

        if (\UPLOAD_ERR_OK === $file->getError()) {
            $name = $file->getClientOriginalName();

            if (false === $type) {
                try {
                    $type = $file->getMimeType();
                } catch (\Exception) {
                    $type = 'application/bin';
                }
            }

            $user = $this->getUsername();

            try {
                $uploadedAsset = $this->fileService->uploadFile($name, $type, Type::string($file->getRealPath()), $user);
            } catch (\Exception $e) {
                $this->logger->error('log.error', [
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);

                return $this->flashMessageLogger->buildJsonResponse([
                    'success' => false,
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'uploaded' => $uploadedAsset->getAvailable() ? 1 : 0,
                'fileName' => $uploadedAsset->getName(),
                EmsFields::CONTENT_FILE_NAME_FIELD_ => $uploadedAsset->getName(),
                EmsFields::CONTENT_FILE_SIZE_FIELD_ => $uploadedAsset->getSize(),
                EmsFields::CONTENT_MIME_TYPE_FIELD_ => $uploadedAsset->getType(),
                EmsFields::CONTENT_FILE_HASH_FIELD_ => $uploadedAsset->getSha1(),
                EmsFields::CONTENT_FILE_ALGO_FIELD_ => $uploadedAsset->getHashAlgo(),
                'url' => $this->generateUrl('ems_file_view', [
                    'sha1' => $uploadedAsset->getSha1(),
                    'name' => $uploadedAsset->getName(),
                    'type' => $uploadedAsset->getType(),
                ]),
            ]);
        } else {
            $this->logger->warning('log.file.upload_error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $file->getError(),
            ]);
            $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => false,
        ]);
    }

    public function browserConfig(): Response
    {
        $response = $this->render('@EMSCore/ems-core/browserconfig.xml.twig', [
            'themeColor' => $this->themeColor,
        ]);
        $response->setCache([
            'max_age' => 3600,
            's_maxage' => 36000,
            'public' => true,
            'private' => false,
        ]);

        return $response;
    }

    public function webManifest(): Response
    {
        $response = $this->render('@EMSCore/ems-core/site.webmanifest.twig', [
            'themeColor' => $this->themeColor,
        ]);
        $response->setCache([
            'max_age' => 3600,
            's_maxage' => 36000,
            'public' => true,
            'private' => false,
        ]);
        $response->headers->set(Headers::CONTENT_TYPE, 'application/manifest+json');

        return $response;
    }

    private function getUsername(): string
    {
        $userObject = $this->getUser();
        if (!$userObject instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('Unexpected User class %s', null === $userObject ? 'null' : $userObject::class));
        }

        return $userObject->getUsername();
    }

    private function closeSession(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->save();
        }
    }

    private function jsonResponse(UploadedAsset $asset, bool $apiRoute): JsonResponse
    {
        $config = ['_config_type' => 'image'];
        if (isset($this->assetConfig['preview'])) {
            $config = \array_merge($this->assetConfig['preview'], $config);
        }
        $config = \array_intersect_key($config, Config::getDefaults());
        unset($config['_published_datetime']);

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'sha1' => $asset->getSha1(),
            'hash' => $asset->getSha1(),
            'type' => $asset->getType(),
            'available' => $asset->getAvailable(),
            'name' => $asset->getName(),
            'size' => $asset->getSize(),
            'status' => $asset->getStatus(),
            'uploaded' => $asset->getUploaded(),
            'user' => $asset->getUser(),
            'fileName' => $asset->getName(),
            'previewUrl' => $this->assetRuntime->assetPath($asset->getData(), $config, 'ems_asset', EmsFields::CONTENT_FILE_HASH_FIELD, EmsFields::CONTENT_FILE_NAME_FIELD, EmsFields::CONTENT_MIME_TYPE_FIELD, UrlGeneratorInterface::ABSOLUTE_PATH),
            'chunkUrl' => $this->generateUrl($apiRoute ? 'emsco_file_api_chunk_upload' : 'emsco_file_data_chunk_upload', ['hash' => $asset->getSha1()]),
            'url' => $this->assetRuntime->assetPath($asset->getData(), [], 'ems_asset', EmsFields::CONTENT_FILE_HASH_FIELD, EmsFields::CONTENT_FILE_NAME_FIELD, EmsFields::CONTENT_MIME_TYPE_FIELD, UrlGeneratorInterface::ABSOLUTE_PATH),
        ]);
    }
}
