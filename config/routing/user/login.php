<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\User\LoginController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_user_login', '/login')
        ->controller([LoginController::class, 'login'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_logout', '/logout')
        ->controller([LoginController::class, 'logout'])
        ->methods(['GET', 'POST']);
};
