<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ElasticsearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('elasticsearch.alias.add', '/alias/add/{name}')
        ->controller([ElasticsearchController::class, 'addAlias'])
        ->methods(['GET', 'POST']);

    $routes->add('elasticsearch.search.delete', '/delete-search/{id}')
        ->controller([ElasticsearchController::class, 'deleteSearch'])
        ->methods(['GET', 'POST']);

    $routes->add('elasticsearch.search.index', '/index-search')
        ->controller([ElasticsearchController::class, 'indexSearch'])
        ->methods(['GET']);

    $routes->add('ems_search_set_default_search_from', '/set-default-search/{id}/{contentType}')
        ->controller([ElasticsearchController::class, 'setDefaultSearch'])
        ->methods(['POST'])
        ->defaults([
            'contentType' => null,
        ]);
};
