<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AbstractController
{
    private FileService $fileService;
    private LoggerInterface $logger;

    public function __construct(FileService $fileService, LoggerInterface $logger)
    {
        $this->fileService = $fileService;
        $this->logger = $logger;
    }

    /**
     * @Route("/data/file/view/{sha1}" , name="ems.file.view", methods={"GET","HEAD"})
     * @Route("/data/file/view/{sha1}" , name="ems_file_view", methods={"GET","HEAD"})
     * @Route("/api/file/view/{sha1}" , name="ems.api.file.view", methods={"GET","HEAD"})
     */
    public function viewFileAction(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
    }

    /**
     * @Route("/public/file/{sha1}" , name="ems_file_download_public", methods={"GET","HEAD"})
     * @Route("/data/file/{sha1}" , name="file.download", methods={"GET","HEAD"})
     * @Route("/data/file/{sha1}" , name="ems_file_download", methods={"GET","HEAD"})
     * @Route("/api/file/{sha1}" , name="file.api.download", methods={"GET","HEAD"})
     */
    public function downloadFileAction(string $sha1, Request $request): Response
    {
        return $this->fileService->getStreamResponse($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
    }

    /**
     * @Route("/admin/file/{id}/delete" , name="ems_file_soft_delete", methods={"POST","HEAD"})
     */
    public function softDeleteFileAction(Request $request, string $id): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException($request->getPathInfo());
        }
        $this->fileService->removeSingleFileEntity([$id]);

        return $this->redirectToRoute('ems_core_uploaded_file_logs');
    }

    /**
     * @Route("/data/file/extract/forced/{sha1}.{_format}" , name="ems_file_extract_forced", defaults={"_format" = "json"}, methods={"GET","HEAD"})
     */
    public function extractFileContentForced(AssetExtractorService $assetExtractorService, Request $request, string $sha1): Response
    {
        return $this->extractFileContent($assetExtractorService, $request, $sha1, true);
    }

    /**
     * @Route("/data/file/extract/{sha1}.{_format}" , name="ems_file_extract", defaults={"_format" = "json"}, methods={"GET","HEAD"})
     */
    public function extractFileContent(AssetExtractorService $assetExtractorService, Request $request, string $sha1, bool $forced = false): Response
    {
        if ($request->hasSession()) {
            $session = $request->getSession();

            if ($session->isStarted()) {
                $session->save();
            }
        }

        try {
            $data = $assetExtractorService->extractData($sha1, null, $forced);
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('Asset %s not found', $sha1));
        }

        $response = $this->render('@EMSCore/ajax/extract-data-file.json.twig', [
            'success' => true,
            'data' => $data,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param string $sha1
     * @param int    $size
     *
     * @return Response
     *
     * @Route("/data/file/init-upload/{sha1}/{size}" , name="file.init-upload", defaults={"_format" = "json", "apiRoute"=false}, methods={"POST"})
     * @Route("/api/file/init-upload/{sha1}/{size}" , name="file.api.init-upload", defaults={"_format" = "json", "apiRoute"=true}, methods={"POST"})
     * @Route("/data/file/init-upload" , name="emsco_file_data_init_upload", defaults={"_format" = "json", "sha1" = null, "size" = null, "apiRoute"=false}, methods={"POST"})
     * @Route("/api/file/init-upload" , name="emsco_file_api_init_upload", defaults={"_format" = "json", "sha1" = null, "size" = null, "apiRoute"=true}, methods={"POST"})
     */
    public function initUploadFileAction($sha1, $size, bool $apiRoute, Request $request)
    {
        if ($sha1 || $size) {
            @\trigger_error('You should use the routes emsco_file_data_init_upload or emsco_file_api_init_upload which doesn\'t require url parameters', E_USER_DEPRECATED);
        }

        $requestContent = $request->getContent();
        if (!\is_string($requestContent)) {
            throw new \RuntimeException('Unexpected body content');
        }

        $params = \json_decode($requestContent, true);
        $name = isset($params['name']) ? $params['name'] : 'upload.bin';
        $type = isset($params['type']) ? $params['type'] : 'application/bin';
        $hash = isset($params['hash']) ? $params['hash'] : $sha1;
        $size = isset($params['size']) ? $params['size'] : $size;
        $algo = isset($params['algo']) ? $params['algo'] : 'sha1';

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

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
        }

        return $this->render('@EMSCore/ajax/file.json.twig', [
            'success' => true,
            'asset' => $uploadedAsset,
            'apiRoute' => $apiRoute,
        ]);
    }

    /**
     * @param string $sha1
     * @param string $hash
     * @param bool   $apiRoute
     *
     * @return Response
     *
     * @Route("/data/file/upload-chunk/{sha1}", name="file.uploadchunk", defaults={"_format" = "json", "hash" = null, "apiRoute"=false}, methods={"POST"})
     * @Route("/api/file/upload-chunk/{sha1}", name="file.api.uploadchunk", defaults={"_format" = "json", "hash" = null, "apiRoute"=true}, methods={"POST"})
     * @Route("/data/file/chunk/{hash}", name="emsco_file_data_chunk_upload", defaults={"_format" = "json", "sha1" = null, "apiRoute"=false}, methods={"POST"})
     * @Route("/api/file/chunk/{hash}", name="emsco_file_api_chunk_upload", defaults={"_format" = "json", "sha1" = null, "apiRoute"=true}, methods={"POST"})
     */
    public function uploadChunkAction($sha1, $hash, $apiRoute, Request $request)
    {
        if ($sha1) {
            $hash = $sha1;
            @\trigger_error('You should use the routes emsco_file_data_chunk_upload or emsco_file_api_chunk_upload which use a hash parameter', E_USER_DEPRECATED);
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

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
        }

        return $this->render('@EMSCore/ajax/file.json.twig', [
            'success' => true,
            'asset' => $uploadedAsset,
            'apiRoute' => $apiRoute,
        ]);
    }

    /**
     * @return Response
     *
     * @Route("/images/index", name="ems_images_index", defaults={"_format"="json"}, methods={"GET", "HEAD"})
     * @Route("/api/images", name="ems_api_images_index", defaults={"_format"="json"}, methods={"GET", "HEAD"})
     */
    public function indexImagesAction()
    {
        $images = $this->fileService->getImages();

        return $this->render('@EMSCore/ajax/images.json.twig', [
            'images' => $images,
        ]);
    }

    /**
     * @return Response
     *
     * @Route("/file/upload", name="ems_image_upload_url", defaults={"_format"="json"}, methods={"POST"})
     * @Route("/api/file", name="ems_api_image_upload_url", defaults={"_format"="json"}, methods={"POST"})
     */
    public function uploadFileAction(Request $request)
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('upload');
        $type = $request->get('type', false);

        if ($file && !$file->getError()) {
            $name = $file->getClientOriginalName();

            if (false === $type) {
                try {
                    $type = $file->getMimeType();
                } catch (Exception $e) {
                    $type = 'application/bin';
                }
            }

            $user = $this->getUsername();

            try {
                $uploadedAsset = $this->fileService->uploadFile($name, $type, $file->getRealPath(), $user);
            } catch (\Exception $e) {
                $this->logger->error('log.error', [
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => false,
                ]);
            }

            return $this->render('@EMSCore/ajax/multipart.json.twig', [
                'success' => true,
                'asset' => $uploadedAsset,
            ]);
        } elseif ($file->getError()) {
            $this->logger->warning('log.file.upload_error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $file->getError(),
            ]);
            $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => false,
        ]);
    }

    private function getUsername(): string
    {
        $userObject = $this->getUser();
        if (!$userObject instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('Unexpected User class %s', null === $userObject ? 'null' : \get_class($userObject)));
        }

        return $userObject->getUsername();
    }
}
