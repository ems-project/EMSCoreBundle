<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UserController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    // Deprecated
    $routes->add('ems.user.index', '/')
        ->controller([UserController::class, 'index'])
        ->methods(['GET']);

    $routes->add('user.add', '/add')
        ->controller([UserController::class, 'addUser'])
        ->methods(['GET', 'POST']);

    $routes->add('user.edit', '/{id}/edit')
        ->controller([UserController::class, 'editUser'])
        ->methods(['GET', 'POST']);

    $routes->add('user.delete', '/{id}/delete')
        ->controller([UserController::class, 'removeUser'])
        ->methods(['POST']);

    $routes->add('user.enabling', '/{id}/enabling')
        ->controller([UserController::class, 'enabling'])
        ->methods(['POST']);

    $routes->add('EMS_user_apikey', '/{username}/apikey')
        ->controller([UserController::class, 'apiKey'])
        ->methods(['POST'])
        ->options(['openapi' => true]);
};
