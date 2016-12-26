<?php

namespace Ems\CoreBundle\Service\Storage;

interface StorageInterface {
	
	public function head($sha1);
	
	public function create($sha1, $filename);
	
	public function read($sha1);
}