<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_admin_environment_remove', '/remove/{environment}')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::remove')
        ->methods(['POST']);

    $routes->add('emsco_admin_environment_add', '/add')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::add')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_edit', '/edit/{environment}')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::edit')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_view', '/{environment}')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::view')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_rebuild', '/rebuild/{environment}')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::rebuild')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_environment_index', '/')
        ->controller('EMS\CoreBundle\Controller\Admin\EnvironmentController::index')
        ->methods(['GET', 'POST']);
};
