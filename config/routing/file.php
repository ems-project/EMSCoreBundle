<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_image_upload_url', '/upload')
        ->controller([FileController::class, 'uploadFile'])
        ->methods(['POST'])
        ->format('json');
};
