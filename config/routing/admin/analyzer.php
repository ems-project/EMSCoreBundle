<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\AnalyzerController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_analyzer_index', '/')
        ->controller([AnalyzerController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_analyzer_edit', '/edit/{analyzer}')
        ->controller([AnalyzerController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_analyzer_delete', '/delete/{analyzer}')
        ->controller([AnalyzerController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_analyzer_add', '/add')
        ->controller([AnalyzerController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_analyzer_export', '/export/{analyzer}.json')
        ->controller([AnalyzerController::class, 'export'])
        ->methods(['GET']);
};
