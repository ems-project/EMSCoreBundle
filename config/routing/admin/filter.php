<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_filter_index', '/')
        ->controller('EMS\CoreBundle\Controller\Admin\FilterController::index')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_edit', '/edit/{filter}')
        ->controller('EMS\CoreBundle\Controller\Admin\FilterController::edit')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_delete', '/delete/{filter}')
        ->controller('EMS\CoreBundle\Controller\Admin\FilterController::delete')
        ->methods(['POST']);

    $routes->add('emsco_filter_add', '/add')
        ->controller('EMS\CoreBundle\Controller\Admin\FilterController::add')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_filter_export', '/export/{filter}.json')
        ->controller('EMS\CoreBundle\Controller\Admin\FilterController::export')
        ->methods(['GET']);
};
