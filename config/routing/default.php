<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\DefaultController;
use EMS\CoreBundle\Controller\ElasticsearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('documentation', '/documentation')
        ->controller([DefaultController::class, 'documentation'])
        ->methods(['GET', 'HEAD']);

    $routes->add('health-check', '/health_check.{_format}')
        ->controller([ElasticsearchController::class, 'status'])
        ->methods(['GET', 'POST'])
        ->requirements(['_format' => 'html|json|xml'])
        ->defaults(['detailed' => false])
        ->format('html');

    $routes->add('elasticsearch.status', '/status.{_format}')
        ->controller([ElasticsearchController::class, 'status'])
        ->methods(['GET', 'POST'])
        ->requirements(['_format' => 'html|json|xml'])
        ->format('html');

    $routes->add('ems_quick_search', '/quick-search')
        ->controller([ElasticsearchController::class, 'quickSearch'])
        ->methods(['GET'])
        ->format('html');
};
