<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Search\SearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_search', '/search')
        ->controller([SearchController::class, 'search'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_search_count', '/count')
        ->controller([SearchController::class, 'count'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_cluster_version', '/version')
        ->controller([SearchController::class, 'version'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_cluster_health_status', '/health-status')
        ->controller([SearchController::class, 'healthStatus'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_scroll_init', '/init-scroll')
        ->controller([SearchController::class, 'initScroll'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_scroll_next', '/next-scroll')
        ->controller([SearchController::class, 'nextScroll'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_index_refresh', '/refresh')
        ->controller([SearchController::class, 'refresh'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_indices_from_alias', '/indices-from-alias')
        ->controller([SearchController::class, 'getIndicesFromAlias'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_indices_from_aliases', '/indices-from-aliases')
        ->controller([SearchController::class, 'getIndicesFromAliases'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_aliases_from_index', '/aliases-from-index')
        ->controller([SearchController::class, 'getAliasesFromIndex'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_get_document', '/document')
        ->controller([SearchController::class, 'getDocument'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_aliases_for_content_type', '/indices-for-content-type')
        ->controller([SearchController::class, 'getIndicesForContentTypes'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_filter_stop_words', '/filter-stop-words')
        ->controller([SearchController::class, 'filterStopWords'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_analyze', '/analyze')
        ->controller([SearchController::class, 'analyze'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_has_index', '/has-index')
        ->controller([SearchController::class, 'hasIndex'])
        ->methods(['POST'])
        ->options(['openapi' => true]);
};
