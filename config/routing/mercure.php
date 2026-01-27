<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\MercureController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_mercure_token', '/mercure/token')
        ->controller([MercureController::class, 'getToken'])
        ->methods(['GET']);
};
