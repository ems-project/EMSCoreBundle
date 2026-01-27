<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\File\ExtractDataController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_extract_data', '/get/{hash}')
        ->controller([ExtractDataController::class, 'get'])
        ->methods(['GET'])
        ->options(['openapi' => true]);
};
