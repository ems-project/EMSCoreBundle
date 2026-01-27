<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\SearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_search_options_index', '/')
        ->controller([SearchController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_sort_option_new', '/sort/new')
        ->controller([SearchController::class, 'newSortOption'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_field_option_new', '/search-field/new')
        ->controller([SearchController::class, 'newSearchFieldOption'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_aggregate_option_new', '/aggregate/new')
        ->controller([SearchController::class, 'newAggregateOption'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_sort_option_edit', '/sort/{id}')
        ->controller([SearchController::class, 'editSortOption'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_field_option_edit', '/search-field/{id}')
        ->controller([SearchController::class, 'editSearchFieldOption'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_search_aggregate_option_edit', '/aggregate/{id}')
        ->controller([SearchController::class, 'editAggregagteOption'])
        ->methods(['GET', 'POST']);
};
