<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UserController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Deprecated
    $routes->add('user.sidebar-collapse', '/sidebar-collapse/{collapsed}')
        ->controller([UserController::class, 'sidebarCollapse'])
        ->methods(['POST']);
};
