<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\NotificationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('notification.ajaxnotification', '/add/{objectId}.json')
        ->controller([NotificationController::class, 'ajaxNotification'])
        ->methods(['POST']);

    $routes->add('notification.cancel', '/cancel/{notification}')
        ->controller([NotificationController::class, 'cancelNotifications'])
        ->methods(['POST']);

    $routes->add('notification.acknowledge', '/acknowledge/{notification}')
        ->controller([NotificationController::class, 'acknowledgeNotifications'])
        ->methods(['POST']);

    $routes->add('notification.treat', '/treat')
        ->controller([NotificationController::class, 'treatNotifications'])
        ->methods(['POST']);

    $routes->add('notification.menu', '/menu')
        ->controller([NotificationController::class, 'menuNotification'])
        ->methods(['GET', 'POST']);

    $routes->add('notifications.list', '/list')
        ->controller([NotificationController::class, 'listNotifications'])
        ->methods(['GET', 'POST'])
        ->defaults(['folder' => 'inbox']);

    $routes->add('notifications.inbox', '/inbox')
        ->controller([NotificationController::class, 'listNotifications'])
        ->methods(['GET', 'POST'])
        ->defaults(['folder' => 'inbox']);

    $routes->add('notifications.sent', '/sent')
        ->controller([NotificationController::class, 'listNotifications'])
        ->methods(['GET', 'POST'])
        ->defaults(['folder' => 'sent']);
};
