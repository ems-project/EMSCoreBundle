<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\QuerySearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_core_query_search_index', '/')
        ->controller([QuerySearchController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_query_search_add', '/add')
        ->controller([QuerySearchController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_query_search_edit', '/edit/{querySearch}')
        ->controller([QuerySearchController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_query_search_delete', '/delete/{querySearch}')
        ->controller([QuerySearchController::class, 'delete'])
        ->methods(['POST']);
};
