<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\EnvironmentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('environment.align', '/align')
        ->controller([EnvironmentController::class, 'align'])
        ->methods(['GET', 'POST']);
};
