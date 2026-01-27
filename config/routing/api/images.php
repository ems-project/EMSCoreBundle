<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('ems_api_images_index', '/images')
        ->controller([FileController::class, 'indexImages'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET', 'HEAD'])
        ->options(['openapi' => true]);
};
