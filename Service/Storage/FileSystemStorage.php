<?php

namespace EMS\CoreBundle\Service\Storage;



use function file_exists;
use function filesize;
use function fopen;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use function unlink;

class FileSystemStorage implements StorageInterface {
	
	private $storagePath;
	
	public function __construct($storagePath) {
		$this->storagePath = $storagePath;
	}

	private function getPath($hash, $cacheContext=null){
		if(!file_exists($this->storagePath)){
			mkdir($this->storagePath, 0777, true);
		}

		$out = $this->storagePath;
		if($cacheContext) {
			$out .= DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.$cacheContext;
		}
		$out.= DIRECTORY_SEPARATOR.substr($hash, 0, 3);
		
		
		if(!file_exists($out) ) {
			mkdir($out, 0777, true);
		}
		
		return $out.DIRECTORY_SEPARATOR.$hash;
	}
	
	public function head($hash, $cacheContext=false) {
		return file_exists($this->getPath($hash, $cacheContext));
	}
	
	public function create($hash, $filename, $cacheContext=false){
		return copy($filename, $this->getPath($hash, $cacheContext));
	}
	
	public function supportCacheStore() {
		return true;
	}

	public function read($hash, $cacheContext=false){
		$out = $this->getPath($hash, $cacheContext);
		if(!file_exists($out)){
			return false;
		}

		return fopen($out, 'rb');
	}
	
	public function getLastUpdateDate($hash, $cacheContext=false){
		$path = $this->getPath($hash, $cacheContext);
		if( file_exists($path)) {
			return @filemtime($path);
		}
		return false;
	}

	public function getSize($hash, $cacheContext = false)
    {
        $path = $this->getPath($hash, $cacheContext);
        if( file_exists($path)) {
            return @filesize($path);
        }
        return false;
    }

    public function __toString()
    {
        return FileSystemStorage::class." ($this->storagePath)";
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove($this->storagePath.DIRECTORY_SEPARATOR.'cache');
        return true;
    }

    public function remove($hash)
    {
        $file = $this->getPath($hash);
        if(file_exists($file))
        {
            unlink($file);
        }
        $finder = new Finder();
        $finder->name($hash);
        foreach ($finder->in($this->storagePath.DIRECTORY_SEPARATOR.'cache') as $file) {
            unlink($file);
        }
        return true;
    }
}
