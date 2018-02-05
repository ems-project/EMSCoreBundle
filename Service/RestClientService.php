<?php

namespace EMS\CoreBundle\Service;

use GuzzleHttp\Client;

class RestClientService
{
	
	/**
	 * 
	 * @param string $baseUrl
	 * @return \GuzzleHttp\Client
	 */
	public function getClient($baseUrl) {
		return new Client([
			// Base URI is used with relative requests
			'base_uri' => $baseUrl,
			// You can set any number of default request options.
			'timeout'  => 1,
		]);
	}
}