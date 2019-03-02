<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class FileController extends AppController
{
	
	
	/**
	 * @Route("/data/file/view/{sha1}" , name="ems.file.view")
	 * @Route("/data/file/view/{sha1}" , name="ems_file_view")
	 * @Route("/api/file/view/{sha1}" , name="ems.api.file.view")
     * @Method({"GET"})
	 */
	public function viewFileAction($sha1, Request $request) {
		return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
	}
	/**
	 * @Route("/public/file/{sha1}" , name="ems_file_download_public")
	 * @Route("/data/file/{sha1}" , name="file.download")
	 * @Route("/data/file/{sha1}" , name="ems_file_download")
	 * @Route("/api/file/{sha1}" , name="file.api.download")
	 * @Method({"GET"})
	 */
	public function downloadFileAction($sha1, Request $request) {
		return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
	}
	
	/**
	 * @Route("/data/file/extract/{sha1}.{_format}" , name="ems_file_extract", defaults={"_format" = "json"})
	 * @Method({"GET"})
	 */
	public function extractFileContent($sha1, Request $request) {
		
		$data = $this->getAssetExtractorService()->extractData($sha1);
		
		$response = $this->render( '@EMSCore/ajax/extract-data-file.json.twig', [
				'success' => true,
				'data' => $data,
		] );
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}
	
	private function getFile($sha1, $disposition, Request $request){
		$name = $request->query->get('name', 'upload.bin');
		$type = $request->query->get('type', 'application/bin');


        $handler = $this->getFileService()->getResource($sha1);

        if(!$handler){
            throw new NotFoundHttpException('Impossible to find the item corresponding to this id: '.$sha1);
        }

        $response = new StreamedResponse(
            function () use ($handler) {
                while (!feof($handler)) {
                    print fread($handler, 8192);
                }
            }, 200, [
                'Content-Disposition' => $disposition.'; '.HeaderUtils::toString(array('filename' => $name), ';'),
                'Content-Type' => $type,
        ]);

        return $response;
	}
	
	
	/**
	 * @Route("/data/file/init-upload/{sha1}/{size}" , name="file.init-upload", defaults={"_format" = "json"})
	 * @Route("/api/file/init-upload/{sha1}/{size}" , name="file.api.init-upload", defaults={"_format" = "json"})
     * @Method({"POST"})
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
		

		return $this->render( '@EMSCore/ajax/file.json.twig', [
				'success' => true,
				'asset' => $uploadedAsset,
		]);
	}
	
	/**
	 * @Route("/data/file/upload-chunk/{sha1}", name="file.uploadchunk", defaults={"_format" = "json"})
	 * @Route("/api/file/upload-chunk/{sha1}", name="file.api.uploadchunk", defaults={"_format" = "json"})
	 */
	public function uploadChunkAction($sha1, Request $request)
	{
		$chunk = $request->getContent();
		$user = $this->getUser()->getUsername();

		try {
			$uploadedAsset = $this->getFileService()->addChunk($sha1, $chunk, $user);
		}
		catch (\Exception $e) {
			$this->addFlash('error', $e->getMessage());
			return $this->render( '@EMSCore/ajax/notification.json.twig', [
					'success' => false,
			]);
		}

		return $this->render( '@EMSCore/ajax/file.json.twig', [
				'success' => true,
				'asset' => $uploadedAsset,
		]);
		
	}
	
	
	
	
	/**
	 * @Route("/images/index" , name="ems_images_index", defaults={"_format": "json"})
	 * @Route("/api/images" , name="ems_api_images_index", defaults={"_format": "json"})
	 * @Method({"GET"})
	 */
	public function indexImagesAction(Request $request) {
		$images = $this->getFileService()->getImages();
		return $this->render( '@EMSCore/ajax/images.json.twig', [
				'images' => $images,
		]);
	}
	
	
	/**
	 * @Route("/file/upload" , name="ems_image_upload_url", defaults={"_format": "json"})
	 * @Route("/api/file" , name="ems_api_image_upload_url", defaults={"_format": "json"})
	 * @Method({"POST"})
	 */
	public function uploadfileAction(Request $request) {
		/**@var UploadedFile $file*/
		$file = $request->files->get('upload');
        $type = $request->get('type', false);
		
		if($file && !$file->getError()){
			
			$name = $file->getClientOriginalName();

			if($type === false){
                try{
                    $type = $file->getMimeType();
                } catch (\Exception $e) {
                    $type = 'application/bin';
                }
            }

			$user = $this->getUser()->getUsername();
			
			try {
				$uploadedAsset = $this->getFileService()->uploadFile($name, $type, $file->getRealPath(), $user);
				
			}
			catch (\Exception $e) {
				$this->addFlash('error', $e->getMessage());
				return $this->render( '@EMSCore/ajax/notification.json.twig', [
						'success' => false,
				]);
			}
			
			
			return $this->render( '@EMSCore/ajax/multipart.json.twig', [
					'success' => true,
					'asset' => $uploadedAsset,
			]);
		}
		else if($file->getError()) {
			$this->addFlash('warning', $file->getError());
			$this->render( '@EMSCore/ajax/notification.json.twig', [
					'success' => false,
			]);
		}
		return $this->render( '@EMSCore/ajax/notification.json.twig', [
				'success' => false,
		]);
	}
	
}