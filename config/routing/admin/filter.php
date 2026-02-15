<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\FilterController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_filter_index', '/')
        ->controller([FilterController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_edit', '/edit/{filter}')
        ->controller([FilterController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_delete', '/delete/{filter}')
        ->controller([FilterController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_filter_add', '/add')
        ->controller([FilterController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_export', '/export/{filter}.json')
        ->controller([FilterController::class, 'export'])
        ->methods(['GET']);
};
