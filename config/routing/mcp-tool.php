<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\McpToolController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add(Routes::MCP_TOOL_INDEX, '/')
        ->controller([McpToolController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_TOOL_ADD, '/add')
        ->controller([McpToolController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_TOOL_EDIT, '/edit/{mcpTool}')
        ->controller([McpToolController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_TOOL_DELETE, '/delete/{mcpTool}')
        ->controller([McpToolController::class, 'delete'])
        ->methods(['POST']);
};
