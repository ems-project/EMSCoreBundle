<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\McpController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_api_mcp', '/mcp')
        ->controller([McpController::class, 'handle'])
        ->methods(['GET', 'POST', 'DELETE', 'OPTIONS']);
};
