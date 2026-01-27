<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('ems.file.view', '/view/{sha1}')
        ->controller([FileController::class, 'viewFile'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_file_view', '/view/{sha1}')
        ->controller([FileController::class, 'viewFile'])
        ->methods(['GET', 'HEAD']);

    $routes->add('file.download', '/{sha1}')
        ->controller([FileController::class, 'downloadFile'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_file_download', '/{sha1}')
        ->controller([FileController::class, 'downloadFile'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_file_extract_forced', '/extract/forced/{sha1}')
        ->controller([FileController::class, 'extractFileContentForced'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_file_extract', '/extract/{sha1}.{_format}')
        ->controller([FileController::class, 'extractFileContent'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET', 'HEAD']);

    $routes->add('file.init-upload', '/init-upload/{sha1}/{size}')
        ->controller([FileController::class, 'initUploadFile'])
        ->defaults(['_format' => 'json', 'apiRoute' => false])
        ->methods(['POST']);

    $routes->add('emsco_file_data_init_upload', '/init-upload')
        ->controller([FileController::class, 'initUploadFile'])
        ->defaults(['_format' => 'json', 'apiRoute' => false, 'sha1' => null, 'size' => null])
        ->methods(['POST']);

    $routes->add('file.uploadchunk', '/upload-chunk/{sha1}')
        ->controller([FileController::class, 'uploadChunk'])
        ->defaults(['_format' => 'json', 'apiRoute' => false, 'hash' => null])
        ->methods(['POST']);

    $routes->add('emsco_file_data_chunk_upload', '/chunk/{hash}')
        ->controller([FileController::class, 'uploadChunk'])
        ->defaults(['_format' => 'json', 'apiRoute' => false, 'sha1' => null])
        ->methods(['POST']);
};
