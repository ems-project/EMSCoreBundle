<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\McpResourceController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add(Routes::MCP_RESOURCE_INDEX, '/')
        ->controller([McpResourceController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_RESOURCE_ADD, '/add')
        ->controller([McpResourceController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_RESOURCE_EDIT, '/edit/{mcpResource}')
        ->controller([McpResourceController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_RESOURCE_DELETE, '/delete/{mcpResource}')
        ->controller([McpResourceController::class, 'delete'])
        ->methods(['POST']);
};
