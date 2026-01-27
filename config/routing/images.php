<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_images_index', '/index')
        ->controller([FileController::class, 'indexImages'])
        ->methods(['GET', 'HEAD'])
        ->format('json');
};
