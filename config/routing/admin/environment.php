<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\EnvironmentController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_admin_environment_remove', '/remove/{environment}')
        ->controller([EnvironmentController::class, 'remove'])
        ->methods(['POST']);

    $routes->add('emsco_admin_environment_add', '/add')
        ->controller([EnvironmentController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_edit', '/edit/{environment}')
        ->controller([EnvironmentController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_view', '/{environment}')
        ->controller([EnvironmentController::class, 'view'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_rebuild', '/rebuild/{environment}')
        ->controller([EnvironmentController::class, 'rebuild'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_index', '/')
        ->controller([EnvironmentController::class, 'index'])
        ->methods(['GET', 'POST']);
};
