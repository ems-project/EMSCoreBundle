<?php

namespace EMS\CoreBundle\Service\Storage;

interface StorageInterface {
	
	public function head($sha1, $cacheContext=false);
	
	public function create($sha1, $filename, $cacheContext=false);
	
	public function read($sha1, $cacheContext=false);
	
	public function getLastUpdateDate($sha1, $cacheContext=false);
	
	public function supportCacheStore();
}