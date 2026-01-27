<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\AssetController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_core_asset_proxy', '/emsch_assets/{requestPath}')
        ->controller([AssetController::class, 'proxyAssetForChannel'])
        ->methods(['GET'])
        ->requirements(['requestPath' => '.+']);

    $routes->add('ems_asset', '/data/asset/{hash_config}/{hash}/{filename}')
        ->controller([AssetController::class, 'asset'])
        ->methods(['GET', 'HEAD']);

    $routes->add('emsco_asset_public', '/public/asset/{hash_config}/{hash}/{filename}')
        ->controller([AssetController::class, 'asset'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_asset_processor', '/asset/{processor}/{hash}')
        ->controller([AssetController::class, 'assetProcessor'])
        ->methods(['GET', 'HEAD']);
};
