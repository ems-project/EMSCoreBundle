<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\ManagedAliasController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_managed_alias_add', '/add')
        ->controller([ManagedAliasController::class, 'add'])
        ->methods(['GET', 'POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_admin_managed_alias_edit', '/edit/{managedAlias}')
        ->controller([ManagedAliasController::class, 'edit'])
        ->methods(['GET', 'POST'])
        ->options(['openapi' => true])
        ->requirements(['id' => '\d+']);

    $routes->add('emsco_admin_managed_alias_delete', '/remove/{managedAlias}')
        ->controller([ManagedAliasController::class, 'remove'])
        ->methods(['POST'])
        ->options(['openapi' => true])
        ->requirements(['id' => '\d+']);
};
