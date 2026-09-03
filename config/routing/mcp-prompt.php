<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\McpPromptController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add(Routes::MCP_PROMPT_INDEX, '/')
        ->controller([McpPromptController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_PROMPT_ADD, '/add')
        ->controller([McpPromptController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_PROMPT_EDIT, '/edit/{mcpPrompt}')
        ->controller([McpPromptController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::MCP_PROMPT_DELETE, '/delete/{mcpPrompt}')
        ->controller([McpPromptController::class, 'delete'])
        ->methods(['POST']);
};
