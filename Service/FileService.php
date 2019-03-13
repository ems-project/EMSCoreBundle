<?php

namespace EMS\CoreBundle\Service;

use const DIRECTORY_SEPARATOR;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UploadedAsset;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Exception\StorageServiceMissingException;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use EMS\CoreBundle\Service\Storage\EntityStorage;
use EMS\CoreBundle\Service\Storage\FileSystemStorage;
use EMS\CoreBundle\Service\Storage\HttpStorage;
use function stream_get_contents;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function unlink;

class FileService {

    private $storageServices;
    
    /**@var Registry*/
    private $doctrine;
    
    private $uploadFolder;
    
    public function __construct(Registry $doctrine, RestClientService $restClient, string $projectDir, string $uploadFolder, string $storageFolder, bool $createDbStorageService, string $elasticmsRemoteServer, string $elasticmsRemoteAuthkey, string $sftpServer, string $sftpPath, string $sftpUser, string $publicKey, string $privateKey)
    {
        $this->doctrine = $doctrine;
        $this->uploadFolder = $uploadFolder;
        $this->storageServices = [];

        if(!empty($storageFolder))
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

        if($createDbStorageService)
        {
            $this->addStorageService(new EntityStorage($doctrine, empty($storageFolder)));
        }

        if(!empty($elasticmsRemoteServer))
        {
            $this->addStorageService(new HttpStorage($restClient, $elasticmsRemoteServer.'/data/file/view/', $elasticmsRemoteServer.'/api/file', $elasticmsRemoteAuthkey));
        }
    }
    
    public function addStorageService($dataField) {
        $this->storageServices[] = $dataField;
    }
    
    public function getStorageService($dataFieldTypeId) {
        return $this->dataFieldTypes[$dataFieldTypeId];
    }
    
    public function getStorages() {
        return $this->storageServices;
    }


    public function getBase64($sha1, $cacheContext=false){
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
            $resource = $service->read($sha1, $cacheContext);
            if($resource){
                $data = stream_get_contents($resource);
                $base64 = base64_encode($data);
                return $base64;
            }
        }
        return false;
    }


    public function getFile($sha1, $cacheContext=false){
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
            $resource = $service->read($sha1, $cacheContext);
            if($resource){
                $filename = tempnam(sys_get_temp_dir(), 'EMS');
                file_put_contents($filename, $resource);
                return $filename;
            }
        }
        return false;
    }
    
    public function getSize($sha1, $cacheContext=false){
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
            $filesize = $service->getSize($sha1, $cacheContext);
            if($filesize !== false){
                return $filesize;
            }
        }
        return false;
    }
    
    public function getLastUpdateDate($sha1, $cacheContext=false){
        $out = false;
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
            $date = $service->getLastUpdateDate($sha1, $cacheContext);
            if($date && ($out === false || $date < $out)){
                $out = $date;
            }
        }
        return $out;
    }
    
    public function head($sha1, $cacheContext=false){
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
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
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
            if($service->create($sha1, $fileName, $cacheContext)) {
                unlink($fileName);
                return true;
            }
        }
        return false;
    }
    
    private function saveFile($filename, UploadedAsset $uploadedAsset){
        if(sha1_file($filename) != $uploadedAsset->getSha1()) {
//             throw new Conflict409Exception("Sha1 mismatched ".sha1_file($filename).' '.$uploadedAsset->getSha1());
//TODO: fix this issue
            $uploadedAsset->setSha1(sha1_file($filename));
            $uploadedAsset->setUploaded(filesize($filename));
        }
        
        /**@var \EMS\CoreBundle\Service\Storage\StorageInterface $service*/
        foreach ($this->storageServices as $service){
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