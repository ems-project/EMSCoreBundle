<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileController extends AbstractController
{
    public function __construct(private readonly FileService $fileService, private readonly AssetExtractorService $assetExtractorService, private readonly LoggerInterface $logger, private readonly string $templateNamespace)
    {
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
        if ($request->hasSession()) {
            $session = $request->getSession();

            if ($session->isStarted()) {
                $session->save();
            }
        }

        try {
            $data = $this->assetExtractorService->extractData($sha1, null, $forced);
        } catch (NotFoundException) {
            throw new NotFoundHttpException(\sprintf('Asset %s not found', $sha1));
        }

        $response = $this->render("@$this->templateNamespace/ajax/extract-data-file.json.twig", [
            'success' => true,
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

    private function getUsername(): string
    {
        $userObject = $this->getUser();
        if (!$userObject instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('Unexpected User class %s', null === $userObject ? 'null' : $userObject::class));
        }

        return $userObject->getUsername();
    }
}
