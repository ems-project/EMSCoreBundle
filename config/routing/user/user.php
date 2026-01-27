<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->import('@EMSCoreBundle/config/routing/user/login.php');
    $routes->import('@EMSCoreBundle/config/routing/user/profile.php');
    $routes->import('@EMSCoreBundle/config/routing/user/resetting.php');
    $routes->import('@EMSCoreBundle/config/routing/user/manage.php')->prefix('/user');
    $routes->import('@EMSCoreBundle/config/routing/user/deprecated.php')->prefix('/user');
};
