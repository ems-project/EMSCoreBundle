<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\UploadedAsset;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileController extends AppController
{
	
	/**
	 * @Route("/data/file/view/{sha1}" , name="ems.file.view")
     * @Method({"GET"})
	 */
	public function viewFileAction($sha1, Request $request) {
		return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_INLINE, $request);
	}
	
	/**
	 * @Route("/data/file/{sha1}" , name="file.download")
     * @Method({"GET"})
	 */
	public function downloadFileAction($sha1, Request $request) {
		return $this->getFile($sha1, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $request);
	}
	
	private function getFile($sha1, $disposition, Request $request){
		$name = $request->query->get('name', 'upload.bin');
		$type = $request->query->get('type', 'application/bin');
		
		$file = $this->getFileService()->getFile($sha1);
		
		
		if(!$file){
			throw new NotFoundHttpException('Impossible to find the item corresponding to this id: '.$sha1);
		}
		
		$response = new BinaryFileResponse($file);
		$response->headers->set('Content-Type', $type);
		$response->setContentDisposition($disposition, $name);
		
		return $response;
	}
	
	
	/**
	 * @Route("/data/file/init-upload/{sha1}/{size}" , name="file.init-upload")
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
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
				'success' => false,
			]);
		}
		

		return $this->render( 'EMSCoreBundle:ajax:file.json.twig', [
				'success' => true,
				'asset' => $uploadedAsset,
		]);
	}
	
	/**
	 * @Route("/data/file/upload-chunk/{sha1}", name="file.uploadchunk")
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
			return $this->render( 'EMSCoreBundle:ajax:notification.json.twig', [
					'success' => false,
			]);
		}

		return $this->render( 'EMSCoreBundle:ajax:file.json.twig', [
				'success' => true,
				'asset' => $uploadedAsset,
		]);
		
	}
	
}