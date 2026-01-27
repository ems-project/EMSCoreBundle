<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\ElasticSearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_elastic_orphan', '/orphan')
        ->controller([ElasticSearchController::class, 'orphanIndexes'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_elastic_orphan_delete', '/orphan/delete/{name}')
        ->controller([ElasticSearchController::class, 'deleteOrphanIndex'])
        ->methods(['POST']);

    $routes->add('emsco_admin_elastic_unreferenced_aliases', '/unreferenced-aliases')
        ->controller([ElasticSearchController::class, 'unreferencedAliases'])
        ->methods(['GET']);

    $routes->add('emsco_admin_elastic_alias_attach', '/alias/attach/{name}')
        ->controller([ElasticSearchController::class, 'attach'])
        ->methods(['POST']);

    $routes->add('emsco_admin_elastic_alias_delete', '/alias/delete/{name}')
        ->controller([ElasticSearchController::class, 'deleteAlias'])
        ->methods(['POST']);
};
