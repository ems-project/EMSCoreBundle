<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ElasticsearchController;
use EMS\CoreBundle\Controller\Search\QuerySearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Query search routes
    $routes->add('elasticsearch.api.search', '/search.json')
        ->controller(QuerySearchController::class)
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_ajax_query_search', '/search.json')
        ->controller(QuerySearchController::class)
        ->methods(['GET']);

    // Elasticsearch export route
    $routes->add('emsco_search_export', '/search/export/{contentType}')
        ->controller([ElasticsearchController::class, 'export'])
        ->methods(['POST']);

    // Elasticsearch search routes
    $routes->add('ems_search', '/search')
        ->controller([ElasticsearchController::class, 'search'])
        ->methods(['GET', 'POST']);

    $routes->add('elasticsearch.search', '/search')
        ->controller([ElasticsearchController::class, 'search'])
        ->methods(['GET', 'POST']);
};
