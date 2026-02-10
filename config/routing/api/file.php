<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_file_hash_algo', '/hash-algo')
        ->controller([FileController::class, 'getHashAlgo'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_file_heads', '/heads')
        ->controller([FileController::class, 'heads'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('ems.api.file.view', '/view/{sha1}')
        ->controller([FileController::class, 'viewFile'])
        ->methods(['GET', 'HEAD'])
        ->options(['openapi' => true]);

    $routes->add('file.api.download', '/{sha1}')
        ->controller([FileController::class, 'downloadFile'])
        ->methods(['GET', 'HEAD'])
        ->options(['openapi' => true]);

    $routes->add('emsco_file_api_init_upload', '/init-upload')
        ->controller([FileController::class, 'initUploadFile'])
        ->defaults(['_format' => 'json', 'apiRoute' => true])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_file_api_chunk_upload', '/chunk/{hash}')
        ->controller([FileController::class, 'uploadChunk'])
        ->defaults(['_format' => 'json', 'apiRoute' => true])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('ems_api_image_upload_url', '/upload')
        ->controller([FileController::class, 'uploadFile'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);
};
