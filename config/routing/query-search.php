<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\QuerySearchController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add(Routes::ADMIN_QUERY_SEARCH_INDEX, '/')
        ->controller([QuerySearchController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::ADMIN_QUERY_SEARCH_ADD, '/add')
        ->controller([QuerySearchController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::ADMIN_QUERY_SEARCH_EDIT, '/edit/{querySearch}')
        ->controller([QuerySearchController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::ADMIN_QUERY_SEARCH_DELETE, '/delete/{querySearch}')
        ->controller([QuerySearchController::class, 'delete'])
        ->methods(['POST']);

    $routes->add(Routes::ADMIN_QUERY_SEARCH_SET_AS_DEFAULT, '/set-as-default/{querySearch}')
        ->controller([QuerySearchController::class, 'setAsDefault'])
        ->methods(['POST']);
};
