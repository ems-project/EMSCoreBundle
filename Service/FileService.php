<?php

namespace EMS\CoreBundle\Service;

class FileService {
	
	private $storageServices;
	
	public function __construct()
	{
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
	
}