<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\ActionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_content_type_action_index', '/{contentType}')
    ->controller([ActionController::class, 'index'])
    ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_action_add', '/{contentType}/add')
    ->controller([ActionController::class, 'add'])
    ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_action_edit', '/{contentType}/edit/{action}.{_format}')
    ->controller([ActionController::class, 'edit'])
    ->defaults(['_format' => 'html'])
    ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_action_delete', '/{contentType}/delete/{action}')
    ->controller([ActionController::class, 'delete'])
    ->methods(['POST']);
};
