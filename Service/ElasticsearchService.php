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
	
	/**
	 * Return a keyword mapping (not analyzed)
	 * @return string[]
	 */
	public function getKeywordMapping() {
		if(version_compare($this->version, '5') > 0){
			return [
					'type' => 'keyword',
			];
		}
		return [
				'type' => 'string',
				'index' => 'not_analyzed'
		];
	}
	
	/**
	 * Return a datetime mapping 
	 * @return string[]
	 */
	public function getDateTimeMapping() {
		return [
			'type' => 'date',
			'format' => 'date_time_no_millis'
		];
	}
	
	/**
	 * Return a not indexed text mapping
	 * @return string[]
	 */
	public function getNotIndexedStringMapping() {
		if(version_compare($this->version, '5') > 0){
			return [
					'type' => 'text',
					'index' => false,
			];
		}
		return [
				'type' => 'string',
				'index' => 'no'
		];
	}
	
}