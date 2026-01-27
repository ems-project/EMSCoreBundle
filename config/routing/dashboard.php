<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\DashboardController as AdminDashboardController;
use EMS\CoreBundle\Controller\Dashboard\DashboardBrowserController;
use EMS\CoreBundle\Controller\DashboardController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_dashboard_home', '/dashboard')
        ->controller([DashboardController::class, 'dashboard'])
        ->methods(['GET']);

    $routes->add('emsco_dashboard', '/dashboard/{name}')
        ->controller([DashboardController::class, 'dashboard'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_dashboard_admin_index', '/admin/dashboard')
        ->controller([AdminDashboardController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_dashboard_admin_add', '/admin/dashboard/add')
        ->controller([AdminDashboardController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_dashboard_admin_edit', '/admin/dashboard/edit/{dashboard}')
        ->controller([AdminDashboardController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_dashboard_admin_delete', '/admin/dashboard/delete/{dashboard}')
        ->controller([AdminDashboardController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_dashboard_admin_define', '/admin/dashboard/{dashboard}/define/{definition}')
        ->controller([AdminDashboardController::class, 'define'])
        ->methods(['POST']);

    $routes->add('emsco_dashboard_admin_undefine', '/admin/dashboard/{dashboard}/undefine')
        ->controller([AdminDashboardController::class, 'undefine'])
        ->methods(['POST']);

    $routes->add('emsco_dashboard_browse', '/dashboard/browse/{dashboardName}')
        ->controller(DashboardBrowserController::class)
        ->methods(['GET']);

    $routes->add('ems_core_dashboard', '/dashboard')
        ->controller([DashboardController::class, 'landing'])
        ->methods(['GET']);
};
