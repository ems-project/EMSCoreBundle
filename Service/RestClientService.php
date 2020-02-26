<?php

namespace EMS\CoreBundle\Service;

use GuzzleHttp\Client;

class RestClientService
{
    public function getClient(string $baseUrl = null, int $timeout = 30) : Client
    {
        $options = [
            'timeout'  => $timeout,
        ];
        if ($baseUrl !== null) {
            $options['base_uri'] = $baseUrl;
        }
        return new Client($options);
    }
}
