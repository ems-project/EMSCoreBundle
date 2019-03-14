<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Controller\AppController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AppController
{
    /** @var RequestRuntime */
    private $requestRuntime;

    public function __construct(RequestRuntime $requestRuntime)
    {
        $this->requestRuntime = $requestRuntime;
    }

	/**
     * @deprecated
	 * @Route("/data/file/view/{sha1}" , name="ems.file.view", methods={"GET","HEAD"})
	 * @Route("/data/file/view/{sha1}" , name="ems_file_view", methods={"GET","HEAD"})
	 * @Route("/api/file/view/{sha1}" , name="ems.api.file.view", methods={"GET","HEAD"})
	 */
	public function viewFileAction($sha1, Request $request) {
	    @trigger_error(sprintf('The "%s::viewFileAction" function is deprecated and should not be used anymore.', FileController::class, AssetController::class), E_USER_DEPRECATED);
	    return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
	}
	/**
     * @deprecated
	 * @Route("/public/file/{sha1}" , name="ems_file_download_public", methods={"GET","HEAD"})
	 * @Route("/data/file/{sha1}" , name="file.download", methods={"GET","HEAD"})
	 * @Route("/data/file/{sha1}" , name="ems_file_download", methods={"GET","HEAD"})
	 * @Route("/api/file/{sha1}" , name="file.api.download", methods={"GET","HEAD"})
	 */
	public function downloadFileAction($sha1, Request $request) {
        @trigger_error(sprintf('The "%s::downloadFileAction" function is deprecated and should not be used anymore.', FileController::class, AssetController::class), E_USER_DEPRECATED);
		return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
	}

	/**
	 * @Route("/data/file/extract/{sha1}.{_format}" , name="ems_file_extract", defaults={"_format" = "json"}, methods={"GET","HEAD"})
	 */
	public function extractFileContent($sha1) {

		$data = $this->getAssetExtractorService()->extractData($sha1);

		$response = $this->render( '@EMSCore/ajax/extract-data-file.json.twig', [
				'success' => true,
				'data' => $data,
		] );
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	private function getFile($sha1, $disposition, Request $request){
        @trigger_error(sprintf('The "%s::getFile" function is deprecated and should not be used anymore.', FileController::class, AssetController::class), E_USER_DEPRECATED);

        return $this->redirect($this->requestRuntime->assetPath([
            EmsFields::CONTENT_FILE_HASH_FIELD => $sha1,
            EmsFields::CONTENT_FILE_NAME_FIELD => $request->query->get('name', 'filename'),
            EmsFields::CONTENT_MIME_TYPE_FIELD => $request->query->get('type', 'application/octet-stream'),
        ], [
            '_disposition' => $disposition,
        ]));
	}


	/**
	 * @Route("/data/file/init-upload/{sha1}/{size}" , name="file.init-upload", defaults={"_format" = "json"}, methods={"POST"})
	 * @Route("/api/file/init-upload/{sha1}/{size}" , name="file.api.init-upload", defaults={"_format" = "json"}, methods={"POST"})
 	 */
	public function initUploadFileAction($sha1, $size, Request $request)
	{
		$params = json_decode($request->getContent(), true);
		$name = isset($params['name']) ? $params['name'] : 'upload.bin';
		$type = isset($params['type']) ? $params['type'] : 'application/bin';
		
		$user = $this->getUser()->getUsername();
		
		try {
			$uploadedAsset = $this->getFileService()->initUploadFile($sha1, $size, $name, $type, $user);
		}
		catch (\Exception $e) {
			$this->addFlash('error', $e->getMessage());
			return $this->render( '@EMSCore/ajax/notification.json.twig', [
				'success' => false,
			]);
		}
		

        return $this->render('@EMSCore/ajax/file.json.twig', [
                'success' => true,
                'asset' => $uploadedAsset,
        ]);
    }

    /**
     * @Route("/data/file/upload-chunk/{sha1}", name="file.uploadchunk", defaults={"_format" = "json"}, methods={"POST"})
     * @Route("/api/file/upload-chunk/{sha1}", name="file.api.uploadchunk", defaults={"_format" = "json"}, methods={"POST"})
     */
    public function uploadChunkAction($sha1, Request $request)
    {
        $chunk = $request->getContent();
        $user = $this->getUser()->getUsername();

        try {
            $uploadedAsset = $this->getFileService()->addChunk($sha1, $chunk, $user);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => false,
            ]);
        }

        return $this->render('@EMSCore/ajax/file.json.twig', [
                'success' => true,
                'asset' => $uploadedAsset,
        ]);
    }




    /**
     * @Route("/images/index" , name="ems_images_index", defaults={"_format": "json"}, methods={"GET","HEAD"})
     * @Route("/api/images" , name="ems_api_images_index", defaults={"_format": "json"}, methods={"GET","HEAD"})
     */
    public function indexImagesAction(Request $request)
    {
        $images = $this->getFileService()->getImages();
        return $this->render('@EMSCore/ajax/images.json.twig', [
                'images' => $images,
        ]);
    }


    /**
     * @Route("/file/upload" , name="ems_image_upload_url", defaults={"_format": "json"}, methods={"POST"})
     * @Route("/api/file" , name="ems_api_image_upload_url", defaults={"_format": "json"}, methods={"POST"})
     */
    public function uploadfileAction(Request $request)
    {
        /**@var UploadedFile $file*/
        $file = $request->files->get('upload');
        $type = $request->get('type', false);

        if ($file && !$file->getError()) {
            $name = $file->getClientOriginalName();

            if ($type === false) {
                try {
                    $type = $file->getMimeType();
                } catch (\Exception $e) {
                    $type = 'application/bin';
                }
            }

            $user = $this->getUser()->getUsername();

            try {
                $uploadedAsset = $this->getFileService()->uploadFile($name, $type, $file->getRealPath(), $user);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }


            return $this->render('@EMSCore/ajax/multipart.json.twig', [
                    'success' => true,
                    'asset' => $uploadedAsset,
            ]);
        } else if ($file->getError()) {
            $this->addFlash('warning', $file->getError());
            $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => false,
            ]);
        }
        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
        ]);
    }
}
