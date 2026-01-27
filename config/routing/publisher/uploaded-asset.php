<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UploadedFileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_uploaded_asset_publisher_overview', '/')
        ->controller([UploadedFileController::class, 'publisherIndex'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_uploaded_asset_publisher_hide', '/delete/{hash}')
        ->controller([UploadedFileController::class, 'publisherHideByHash'])
        ->methods(['POST']);
};
