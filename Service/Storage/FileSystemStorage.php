<?php

namespace Ems\CoreBundle\Service\Storage;



class FileSystemStorage implements StorageInterface {
	
	private $storagePath;
	
	public function __construct($storagePath, $kernel_root_dir) {
		$this->storagePath = $storagePath;
		if(!$storagePath) {
			$this->storagePath = $kernel_root_dir.'/../var/assets/';
		}
		if(!file_exists($this->storagePath)){
			mkdir($this->storagePath, 0777, true);
		}
	}
	
	private function getPath($sha1){
		if(! file_exists($this->storagePath.'/'.substr($sha1, 0, 3)) ){
			mkdir($this->storagePath.'/'.substr($sha1, 0, 3));
		}
		return $this->storagePath.'/'.substr($sha1, 0, 3).'/'.$sha1;
	}
	
	public function head($sha1) {
		return file_exists($this->getPath($sha1));
	}
	
	public function create($sha1, $filename){
		return rename($filename, $this->getPath($sha1));
	}
	
	public function read($sha1){
		$out = $this->getPath($sha1);
		if(!file_exists($out)){
			return false;
		}
		return $out;
	}
}
