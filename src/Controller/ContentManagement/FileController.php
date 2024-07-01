<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\File\File;
use EMS\Helpers\Html\Headers;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileController extends AbstractController
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly AssetExtractorService $assetExtractorService,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace,
        private readonly string $themeColor,
    ) {
    }

    public function viewFileAction(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
    }

    public function downloadFileAction(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
    }

    public function softDeleteFileAction(Request $request, string $id): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException($request->getPathInfo());
        }
        $this->fileService->removeSingleFileEntity([$id]);

        return $this->redirectToRoute('ems_core_uploaded_file_logs');
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

        $response = $this->render("@$this->templateNamespace/ajax/extract-data-file.json.twig", [
            'success' => !$data->isEmpty(),
            'data' => $data,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param int $size
     *
     * @deprecated
     */
    public function initUploadFileAction(?string $sha1, $size, bool $apiRoute, Request $request): Response
    {
        if ($sha1 || $size) {
            @\trigger_error('You should use the routes emsco_file_data_init_upload or emsco_file_api_init_upload which doesn\'t require url parameters', E_USER_DEPRECATED);
        }

        $requestContent = $request->getContent();
        if (!\is_string($requestContent)) {
            throw new \RuntimeException('Unexpected body content');
        }

        $params = \json_decode($requestContent, true, 512, JSON_THROW_ON_ERROR);
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

            return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
                'success' => false,
            ]);
        }

        return $this->render("@$this->templateNamespace/ajax/file.json.twig", [
            'success' => true,
            'asset' => $uploadedAsset,
            'apiRoute' => $apiRoute,
        ]);
    }

    /** @deprecated */
    public function uploadChunkAction(?string $sha1, ?string $hash, bool $apiRoute, Request $request): Response
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

            return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
                'success' => false,
            ]);
        }

        return $this->render("@$this->templateNamespace/ajax/file.json.twig", [
            'success' => true,
            'asset' => $uploadedAsset,
            'apiRoute' => $apiRoute,
        ]);
    }

    public function indexImagesAction(): Response
    {
        $images = $this->fileService->getImages();

        return $this->render("@$this->templateNamespace/ajax/images.json.twig", [
            'images' => $images,
        ]);
    }

    public function icon(Request $request, int $width, int $height, string $background = null): Response
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

    public function uploadFileAction(Request $request): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('upload');
        $type = $request->get('type', false);

        if (\UPLOAD_ERR_OK === $file->getError()) {
            $name = $file->getClientOriginalName();

            if (false === $type) {
                try {
                    $type = $file->getMimeType();
                } catch (\Exception $e) {
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

                return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
                    'success' => false,
                ]);
            }

            return $this->render("@$this->templateNamespace/ajax/multipart.json.twig", [
                'success' => true,
                'asset' => $uploadedAsset,
            ]);
        } else {
            $this->logger->warning('log.file.upload_error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $file->getError(),
            ]);
            $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
                'success' => false,
            ]);
        }

        return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
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
}
