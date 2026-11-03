<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\UserController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_admin_user_create', '/create')
        ->controller([UserController::class, 'create'])
        ->methods(['POST']);

    $routes->add('emsco_admin_user_delete', '/delete')
        ->controller([UserController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_admin_user_edit', '/edit')
        ->controller([UserController::class, 'edit'])
        ->methods(['POST']);

    $routes->add('emsco_admin_user_reset_password', '/reset-password')
        ->controller([UserController::class, 'resetPasswordUrl'])
        ->methods(['POST']);
};
