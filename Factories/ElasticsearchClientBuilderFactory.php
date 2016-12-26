<?php

namespace Ems\CoreBundle\Factories;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;

/**
 * elasticSearch Factory.
 */
class ElasticsearchClientBuilderFactory

{	

	//TODO consider the following configurations (most won't be usefull):
	//=== Authorization and Encryption
	//=== Set retries (defaults to the number of nodes)
	//=== Enabling the Logger (requires "monolog/monolog": "~1.0")
	//=== Configure the HTTP Handler
	//=== Setting the Connection Pool
	//=== Setting the Connection Selector
	//=== Setting the Serializer
	//=== Setting a custom ConnectionFactory
	//=== Set the Endpoint closure
	/**
     * @return Client
	 */
	public static function build($hosts){
		$params = [];
		
		if (isset($hosts)){
			$params['hosts'] = $hosts;
		}
		
		
		return ClientBuilder::fromConfig($params);
	}
}