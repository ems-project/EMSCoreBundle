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
    public function getClient($baseUrl = null, int $timeout = 30)
    {
        $options = [
            // You can set any number of default request options.
            'timeout'  => $timeout,
        ];
        if ($baseUrl) {
            // Base URI is used with relative requests
            $options['base_uri'] = $baseUrl;
        }
        return new Client($options);
    }
}
