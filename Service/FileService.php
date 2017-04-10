<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UploadedAsset;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Exception\StorageServiceMissingException;
use Elasticsearch\Common\Exceptions\Conflict409Exception;

class FileService {

	private $storageServices;
	/**@var Registry*/
	private $doctrine;
	
	public function __construct(Registry $doctrine)
	{
		$this->doctrine = $doctrine;
		$this->storageServices = [];
	}
	
	public function addStorageService($dataField) {
		$this->storageServices[ get_class($dataField) ] = $dataField;
	}
	
	public function getStorageService($dataFieldTypeId) {
		return $this->dataFieldTypes[$dataFieldTypeId];
	}


	public function getBase64($sha1){
		/**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
		foreach ($this->storageServices as $service){
			$filename = $service->read($sha1);
			if($filename){
				$data = file_get_contents($filename);
				$base64 = base64_encode($data);
				return $base64;
			}
		}
		return false;
	}


	public function getFile($sha1){
		/**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
		foreach ($this->storageServices as $service){
			$filename = $service->read($sha1);
			if($filename){
				return $filename;
			}
		}
		return false;
	}
	
	public function getSize($sha1){
		/**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
		foreach ($this->storageServices as $service){
			$filename = $service->read($sha1);
			if($filename){
				return filesize($filename);
			}
		}
		return false;
	}
	
	public function head($sha1){
		/**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
		foreach ($this->storageServices as $service){
			if($service->head($sha1)){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 
	 * @param string $sha1
	 * @param integer $size
	 * @param string $name
	 * @param string $type
	 * @param User $user
	 * @throws StorageServiceMissingException
	 * @throws Conflict409Exception
	 * @return UploadedAsset
	 */
	public function initUploadFile($sha1, $size, $name, $type, $user){
		if(empty($this->storageServices)){
			throw new StorageServiceMissingException("No storage service have been defined");
		}
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager();
		/** @var UploadedAssetRepository $repository */
		$repository = $em->getRepository( 'EMSCoreBundle:UploadedAsset' );
		
		
		/**@var UploadedAsset $uploadedAsset*/
		$uploadedAsset = $repository->findOneBy([
				'sha1' => $sha1,
				'available' => false,
				'user' => $user,
		]);
		
		if(!$uploadedAsset) {
			$uploadedAsset = new UploadedAsset();
			$uploadedAsset->setSha1($sha1);
			$uploadedAsset->setUser($user);
			$uploadedAsset->setSize($size);
			$uploadedAsset->setUploaded(0);
		}
		

		$uploadedAsset->setType($type);
		$uploadedAsset->setName($name);
		$uploadedAsset->setAvailable(false);
		
		if($uploadedAsset->getSize() != $size){
			throw new Conflict409Exception("Target size mismatched ".$uploadedAsset->getSize().' '.$size);
		}
		
		if($this->head($sha1)) {
			if($this->getSize($sha1) != $size){ //one is a string the other is a number
				throw new Conflict409Exception("Hash conflict");	
			}
			$uploadedAsset->setUploaded($uploadedAsset->getSize());
			$uploadedAsset->setAvailable(true);
		}
		else {
			//Get temporyName
			$filename = $this->temporaryFilename($sha1);
			if(file_exists($filename)) {
				if(filesize($filename) !== $uploadedAsset->getUploaded()){
					file_put_contents($filename, "");
					$uploadedAsset->setUploaded(0);
				}
				else {
					$uploadedAsset = $this->saveFile($filename, $uploadedAsset);
				}
			}
			else {
				touch($filename);
				$uploadedAsset->setUploaded(0);
			}
		}

		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		
		return $uploadedAsset;
	}
	
	
	public function addChunk($sha1, $chunk, $user) {
		if(empty($this->storageServices)){
			throw new StorageServiceMissingException("No storage service have been defined");
		}
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager ();
		/** @var UploadedAssetRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:UploadedAsset' );
		
		
		/**@var UploadedAsset $uploadedAsset*/
		$uploadedAsset = $repository->findOneBy([
				'sha1' => $sha1,
				'available' => false,
				'user' => $user,
		]);
		
		if(!$uploadedAsset) {
			throw new NotFoundHttpException('Upload job not found');
		}
		
		
		$filename = $this->temporaryFilename($sha1);
		if(!file_exists($filename)) {
			throw new NotFoundHttpException('tempory file not found');
		}
		
		$myfile = fopen($filename, "a");
		$result = fwrite($myfile, $chunk);
		fflush($myfile);
		fclose($myfile);
		
		$uploadedAsset->setUploaded(filesize($filename));
		
		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		
		if($uploadedAsset->getUploaded() == $uploadedAsset->getSize()){
			$uploadedAsset = $this->saveFile($filename, $uploadedAsset);
		}
		
		return $uploadedAsset;
	}
	
	private function saveFile($filename, UploadedAsset $uploadedAsset){
		if(sha1_file($filename) != $uploadedAsset->getSha1()) {
			throw new Conflict409Exception("Sha1 mismatched ".sha1_file($filename).' '.$uploadedAsset->getSha1());
		}
		
		/**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
		foreach ($this->storageServices as $service){
			if($service->create($uploadedAsset->getSha1(), $filename)) {
				$uploadedAsset->setAvailable(true);
				break;
			}
		}
		
		return $uploadedAsset;
	}
	
	/**
	 * return temporary filename
	 * @param string $sha1
	 * @return string
	 */
	private function temporaryFilename($sha1) {
		return sys_get_temp_dir().DIRECTORY_SEPARATOR.$sha1;
	}
}