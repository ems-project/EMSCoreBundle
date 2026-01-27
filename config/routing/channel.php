<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\ChannelController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_core_channel_index', '/')
        ->controller([ChannelController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_channel_add', '/add')
        ->controller([ChannelController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_channel_edit', '/edit/{channel}')
        ->controller([ChannelController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_channel_delete', '/delete/{channel}')
        ->controller([ChannelController::class, 'delete'])
        ->methods(['POST']);
};
