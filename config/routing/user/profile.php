<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\User\ProfileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_user_profile', '/profile')
        ->controller([ProfileController::class, 'show'])
        ->methods(['GET']);

    $routes->add('emsco_user_profile_edit', '/profile/edit')
        ->controller([ProfileController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_change_password', '/profile/change-password')
        ->controller([ProfileController::class, 'changePassword'])
        ->methods(['GET', 'POST']);
};
