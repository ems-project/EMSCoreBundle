<?php

namespace EMS\CoreBundle\Service;

use const DIRECTORY_SEPARATOR;
use EMS\CommonBundle\Storage\Service\EntityStorage;
use EMS\CommonBundle\Storage\Service\FileSystemStorage;
use EMS\CommonBundle\Storage\Service\HttpStorage;
use EMS\CommonBundle\Storage\Service\S3Storage;
use EMS\CommonBundle\Storage\Service\SftpStorage;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Storage\StorageServiceMissingException;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UploadedAsset;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileService {
	
	/**@var Registry*/
	private $doctrine;

    private $uploadFolder;

    /**@var StorageManager*/
    private $storageManager;
	
	public function __construct(Registry $doctrine, StorageManager $storageManager, string $projectDir, string $uploadFolder, string $storageFolder, bool $createDbStorageService, string $elasticmsRemoteServer, string $elasticmsRemoteAuthkey, string $sftpServer, string $sftpPath, string $sftpUser, string $publicKey, string $privateKey, array $s3Credentials=null, string $s3Bucket=null)
	{
	    $this->doctrine = $doctrine;
	    $this->uploadFolder = $uploadFolder;
        $this->storageManager = $storageManager;

        if($storageFolder && !empty($storageFolder))
        {
            if(substr($storageFolder, 0, 2) === ('.'.DIRECTORY_SEPARATOR))
            {
                $this->addStorageService(new FileSystemStorage($projectDir.substr($storageFolder, 1)));
            }
            else
            {
                $this->addStorageService(new FileSystemStorage($storageFolder));
            }
        }

        if(!empty($s3Credentials) && !empty($s3Bucket))
        {
            $this->addStorageService(new S3Storage($s3Credentials, $s3Bucket));
        }


        if($createDbStorageService)
        {
            $this->addStorageService(new EntityStorage($doctrine, empty($storageFolder)));
        }

        if(!empty($elasticmsRemoteServer))
        {
            $this->addStorageService(new HttpStorage($elasticmsRemoteServer.'/data/file/view/', $elasticmsRemoteServer.'/api/file', $elasticmsRemoteAuthkey));
        }

        if(!empty($sftpServer) && !empty($sftpPath) && !empty($sftpPath) && !empty($publicKey) && !empty($privateKey))
        {
            $this->addStorageService(new SftpStorage($sftpServer, $sftpPath, $sftpUser, $publicKey, $privateKey, true));
        }

	}
	
	public function addStorageService($storageAdapter) {
	    $this->storageManager->addAdapter($storageAdapter);
	}
	
	public function getStorageService(StorageInterface $dataFieldTypeId)
    {
		return $this->dataFieldTypes($dataFieldTypeId);
	}
	
	public function getStorages() {
		return $this->storageManager->getAdapters();
	}


	public function getBase64($sha1, $cacheContext=false){
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			$resource = $service->read($sha1, $cacheContext);
			if($resource){
				$data = stream_get_contents($resource);
				$base64 = base64_encode($data);
				return $base64;
			}
		}
		return false;
	}



    public function getResource($hash, $cacheContext=false){
        /**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
        foreach ($this->storageManager->getAdapters() as $service){
            $resource = $service->read($hash, $cacheContext);
            if($resource){
                return $resource;
            }
        }
        return false;
    }

    /**
     * @deprecated
     * @param $sha1
     * @param bool $cacheContext
     * @return bool|string
     */
	public function getFile($sha1, $cacheContext=false){
        $resource = $this->getResource($sha1, $cacheContext);
        if($resource)
        {
            $filename = tempnam(sys_get_temp_dir(), 'EMS');
            file_put_contents($filename, $resource);
            return $filename;
        }
		return false;
	}
	
	public function getSize($sha1, $cacheContext=false){
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			$filesize = $service->getSize($sha1, $cacheContext);
			if($filesize !== false){
				return $filesize;
			}
		}
		return false;
	}

    /**
     * @param string      $hash
     * @param string|null $context
     *
     * @return null|\DateTime
     */
    public function getLastUpdateDate(string $hash, ?string $context = null): ?\DateTime
    {
		$out = null;
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			$date = $service->getLastUpdateDate($hash, $context);
			if($date && ($out === null || $date < $out)){
				$out = $date;
			}
		}
		return $out;
	}
	
	public function head($sha1, $cacheContext=false){
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			if($service->head($sha1, $cacheContext)){
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
		if(empty($this->storageManager->getAdapters())){
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
			$filename = $this->temporaryUploadFilename($sha1);
			if(file_exists($filename)) {
			    if(filesize($filename) !== intval($uploadedAsset->getUploaded()) || $uploadedAsset->getUploaded() == $uploadedAsset->getSize()){
					file_put_contents($filename, "");
					$uploadedAsset->setUploaded(0);
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
	
	
	public function getImages() {
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager ();
		/** @var UploadedAssetRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:UploadedAsset' );
		

		 $qb = $repository
			->createQueryBuilder('a')->where('a.type like :image')
			->select('a.type, a.name, a.sha1, a.user')
			->setParameter('image', 'image/%')
			->groupBy('a.type, a.name, a.sha1, a.user');
			
		$query = $qb->getQuery();
		
		
		$result = $query->getResult();
		return $result;
	}
		
	public function uploadFile($name, $type, $filename, $user) {
		
		$sha1 = sha1_file($filename);
		$size = filesize($filename);
		$uploadedAsset = $this->initUploadFile($sha1, $size, $name, $type, $user);
		if(!$uploadedAsset->getAvailable()) {
			$uploadedAsset = $this->saveFile($filename, $uploadedAsset);
		}
		
		/** @var EntityManager $em */
		$em = $this->doctrine->getManager ();
		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		
		return $uploadedAsset;
	}
	
	
	public function addChunk($sha1, $chunk, $user) {
		if(empty($this->storageManager->getAdapters())){
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
		
		
		$filename = $this->temporaryUploadFilename($sha1);
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
		
		$em->persist($uploadedAsset);
		$em->flush($uploadedAsset);
		return $uploadedAsset;
	}
	
	
	public function create($sha1, $fileName, $cacheContext=false) {
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			if($service->create($sha1, $fileName, $cacheContext)) {
			    unlink($fileName);
				return true;
			}
		}
		return false;
	}
	
	private function saveFile($filename, UploadedAsset $uploadedAsset){
		if(sha1_file($filename) != $uploadedAsset->getSha1()) {
// 			throw new Conflict409Exception("Sha1 mismatched ".sha1_file($filename).' '.$uploadedAsset->getSha1());
//TODO: fix this issue by using the CryotJS librairy on the FE JS?
			$uploadedAsset->setSha1(sha1_file($filename));
			$uploadedAsset->setUploaded(filesize($filename));
		}
		
		/**@var \EMS\CommonBundle\Storage\Service\StorageInterface $service*/
		foreach ($this->storageManager->getAdapters() as $service){
			if($service->create($uploadedAsset->getSha1(), $filename)) {
				$uploadedAsset->setAvailable(true);
                unlink($filename);
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
    private function temporaryUploadFilename($sha1) {
        if($this->uploadFolder){
            if(!is_dir($this->uploadFolder)) {
                mkdir ( $this->uploadFolder , 0777, true);
            }

            return $this->uploadFolder.DIRECTORY_SEPARATOR.$sha1;
        }
        return $this->temporaryFilename($sha1);
    }

    /**
     * return temporary filename
     * @param string $sha1
     * @return string
     */
    public function temporaryFilename($sha1) {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$sha1;
    }
}