<?php

namespace Ems\CoreBundle\Controller\ContentManagement;

use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle;
use Ems\CoreBundle\Entity\UploadedAsset;
use Ems\CoreBundle\Repository\UploadedAssetRepository;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FileController extends AppController
{
	
	/**
	 * @Route("/data/file/{sha1}" , name="file.download")
     * @Method({"GET"})
	 */
	public function downloadFileAction($sha1, Request $request) {

		$name = $request->query->get('name', 'upload.bin');
		$type = $request->query->get('type', 'application/bin');
		
		$file = false;
		
		foreach ($this->getParameter('storage_services') as $serviceName){
			/**@var \Ems\CoreBundle\Service\Storage\StorageInterface $service */
			$service = $this->get($serviceName);
			$file = $service->read($sha1);
			if($file) {
				break;
			}
		}
		
		if(!$file){
			throw new NotFoundHttpException('Impossible to find the item corresponding to this id: '.$sha1);
		}
		
		$response = new BinaryFileResponse($file);
		$response->headers->set('Content-Type', $type);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name);
		
		return $response;
	}
	
	/**
	 * @Route("/data/file/init-upload/{sha1}/{size}" , name="file.init-upload")
     * @Method({"POST"})
	 */
	public function initUploadFileAction($sha1, $size, Request $request)
	{
		

		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var UploadedAssetRepository $repository */
		$repository = $em->getRepository ( 'Ems/CoreBundle:UploadedAsset' );
		
		$user = $this->getUser()->getUsername();
		
		/**@var UploadedAsset $uploadedAsset*/
		$uploadedAsset = $repository->findOneBy([
			'sha1' => $sha1,
			'available' => false,
			'user' => $user,
			'available' => false,
		]);
		
		if(!$uploadedAsset) {
			$uploadedAsset = new UploadedAsset();
			$uploadedAsset->setSha1($sha1);
			$uploadedAsset->setUser($user);
			$uploadedAsset->setSize($size);
			$uploadedAsset->setUploaded(0);
				
		}
		
		$params = json_decode($request->getContent(), true);
		$uploadedAsset->setName('upload.bin');
		if(isset($params['name'])){
			$uploadedAsset->setName($params['name']);			
		}
		$uploadedAsset->setType('application/bin');
		if(isset($params['type'])){
			$uploadedAsset->setType($params['type']);			
		}
		$uploadedAsset->setAvailable(false);
		
		if($uploadedAsset->getSize() != $size){
			throw new Conflict409Exception("Target size mismatched ".$uploadedAsset->getSize().' '.$size);
		}
		
		//TODO check if the file can be found in the repository
		foreach ($this->getParameter('storage_services') as $serviceName){
			if($this->get($serviceName)->head($uploadedAsset->getSha1())) {
				$uploadedAsset->setUploaded($uploadedAsset->getSize());
				$uploadedAsset->setAvailable(true);
				
				$em->persist($uploadedAsset);
				$em->flush($uploadedAsset);

				return new JsonResponse($uploadedAsset->getResponse());
			}
		}
		
		
		
		//Get temporyName
		$filename = $this->filename($sha1);
		

		if(file_exists($filename)) {
			$alreadyUploaded = filesize($filename);
			if($alreadyUploaded !== $uploadedAsset->getUploaded()){
				file_put_contents($filename, "");
				$uploadedAsset->setUploaded(0);
			}
			else{
				$uploadedAsset->setUploaded($alreadyUploaded);
			}
		}
		else {
			touch($filename);
			$uploadedAsset->setUploaded(0);
		}
		
		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		
		return new JsonResponse($uploadedAsset->getResponse());
	}
	
	private function filename($sha1) {
		$target = $this->getParameter('uploading_folder');
		if(!$target) {
			$target = sys_get_temp_dir();
		}
		
		return $target.'/'.$sha1;
		
	}
	
	/**
	 * @Route("/data/file/upload-chunk/{sha1}", name="file.uploadchunk")
	 */
	public function uploadChunkAction($sha1, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var UploadedAssetRepository $repository */
		$repository = $em->getRepository ( 'Ems/CoreBundle:UploadedAsset' );
		
		$user = $this->getUser()->getUsername();
		
		/**@var UploadedAsset $uploadedAsset*/
		$uploadedAsset = $repository->findOneBy([
				'sha1' => $sha1,
				'available' => false,
				'user' => $user,
				'available' => false,
		]);
		
		if(!$uploadedAsset) {
			throw new NotFoundHttpException('Upload job not found');
		}
		
		
		$filename = $this->filename($sha1);
		if(!file_exists($filename)) {
			throw new NotFoundHttpException('tempory file not found');
		}		
		$content = $request->getContent();
		
		$myfile = fopen($filename, "a");
		$result = fwrite($myfile, $content);
		fflush($myfile);
		fclose($myfile);
		
		$uploadedAsset->setUploaded(filesize($filename));
		
		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		
		if($uploadedAsset->getUploaded() == $uploadedAsset->getSize()){

			if(sha1_file($filename) != $uploadedAsset->getSha1()) {
				throw new Conflict409Exception("Sha1 mismatched ".sha1_file($filename).' '.$uploadedAsset->getSha1());
			}
			
			foreach ($this->getParameter('storage_services') as $serviceName){
				if($this->get($serviceName)->create($uploadedAsset->getSha1(), $filename)) {
					$uploadedAsset->setAvailable(true);
					break;
				}
			}
			
		}
		
		return new JsonResponse($uploadedAsset->getResponse());
	}
	
}