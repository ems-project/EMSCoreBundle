<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\User\GroupController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_group_admin_index', '/admin/group')
        ->controller([GroupController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_group_admin_add', '/admin/group/add')
        ->controller([GroupController::class, 'addGroup'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_group_admin_delete', '/admin/group/delete/{group}')
        ->controller([GroupController::class, 'deleteGroup'])
        ->methods(['POST']);

    $routes->add('emsco_group_admin_edit', '/admin/group/edit/{group}')
        ->controller([GroupController::class, 'editGroup'])
        ->methods(['GET', 'POST']);
};
