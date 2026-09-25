<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ElasticsearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('elasticsearch.alias.add', '/alias/add/{name}')
        ->controller([ElasticsearchController::class, 'addAlias'])
        ->methods(['GET', 'POST']);
};
