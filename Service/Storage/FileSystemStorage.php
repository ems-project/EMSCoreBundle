<?php

namespace EMS\CoreBundle\Service\Storage;



use function filesize;
use function fopen;

class FileSystemStorage implements StorageInterface {
	
	private $storagePath;
	
	public function __construct($storagePath, $kernel_root_dir) {
		$this->storagePath = $storagePath;
		if(empty($storagePath)) {
			$this->storagePath = $kernel_root_dir.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'assets';
		}
		if(!file_exists($this->storagePath)){
			mkdir($this->storagePath, 0777, true);
		}
	}
	
	private function getPath($sha1, $cacheContext){
		$out = $this->storagePath;
		if($cacheContext) {
			$out .= DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$cacheContext;
		}
		$out.= DIRECTORY_SEPARATOR.substr($sha1, 0, 3);
		
		
		if(!file_exists($out) ) {
			mkdir($out, 0777, true);
		}
		
		return $out.DIRECTORY_SEPARATOR.$sha1;
	}
	
	public function head($sha1, $cacheContext=false) {
		return file_exists($this->getPath($sha1, $cacheContext));
	}
	
	public function create($sha1, $filename, $cacheContext=false){
		return copy($filename, $this->getPath($sha1, $cacheContext));
	}
	
	public function supportCacheStore() {
		return true;
	}

	public function read($sha1, $cacheContext=false){
		$out = $this->getPath($sha1, $cacheContext);
		if(!file_exists($out)){
			return false;
		}

		return fopen($out, 'rb');
	}
	
	public function getLastUpdateDate($sha1, $cacheContext=false){
		$path = $this->getPath($sha1, $cacheContext);
		if( file_exists($path)) {
			return @filemtime($path);
		}
		return false;
	}

	public function getSize($sha1, $cacheContext = false)
    {
        $path = $this->getPath($sha1, $cacheContext);
        if( file_exists($path)) {
            return @filesize($path);
        }
        return false;
    }
}
