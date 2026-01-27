<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\User\ResettingController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_user_resetting_request', '/resetting/request')
        ->controller([ResettingController::class, 'request'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_resetting_check_email', '/resetting/check_email')
        ->controller([ResettingController::class, 'checkEmail'])
        ->methods(['GET']);

    $routes->add('emsco_user_resetting_reset', '/resetting/reset/{token}')
        ->controller([ResettingController::class, 'reset'])
        ->methods(['GET', 'POST']);
};
