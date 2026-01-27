<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UploadedFileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_uploaded_asset_admin_overview', '/')
        ->controller([UploadedFileController::class, 'adminOverview'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_uploaded_asset_admin_toggle_visibility', '/show-hide/{assetId}')
        ->controller([UploadedFileController::class, 'adminToggleVisibility'])
        ->methods(['POST']);

    $routes->add('emsco_uploaded_asset_admin_delete', '/delete/{uploadedAsset}')
        ->controller([UploadedFileController::class, 'adminDelete'])
        ->methods(['POST']);
};
