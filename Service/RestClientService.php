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
    public function getClient($baseUrl=NULL) {
        $options = [
            // You can set any number of default request options.
            'timeout'  => 30,
        ];
        if($baseUrl){
            // Base URI is used with relative requests
            $options['base_uri'] = $baseUrl;
        }
        return new Client($options);
    }
}