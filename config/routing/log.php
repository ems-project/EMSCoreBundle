<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Log\LogController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_log_index', '/index')
        ->controller([LogController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_log_delete', '/delete/{log}')
        ->controller([LogController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_log_view', '/view/{log}')
        ->controller([LogController::class, 'view'])
        ->methods(['GET']);
};
