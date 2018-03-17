<?php

namespace EMS\CoreBundle\Service;

class ElasticsearchService {
	
	/**
	 * The elasticsearch version as specified in the bundle parameter
	 * 
	 * @var string
	 */
	private $version;
	
	/**
	 * Constructor
	 * 
	 * @param string $version
	 */
	public function __construct($version)
	{
		$this->version = $version;;
	}
	
	/**
	 * Returns the parameter specified version
	 * 
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}
	
	/**
	 * Compare the paramter specified version with a string
	 * 
	 * @param string $version
	 * @return mixed
	 */
	public function compare($version) {
		return version_compare($this->version, $version);
	}
	
}