<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UserController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_user_index', '/')
        ->controller([UserController::class, 'index'])
        ->methods(['GET']);

    $routes->add('user.permissions', '/permissions')
        ->controller([UserController::class, 'contentTypePermissions'])
        ->methods(['GET']);

    $routes->add('permissions.view', '/permissions/{contentType}')
        ->controller([UserController::class, 'contentTypeFieldsPermissions'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_add', '/add')
        ->controller([UserController::class, 'addUser'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_edit', '/edit/{user}')
        ->controller([UserController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_user_delete', '/delete/{user}')
        ->controller([UserController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_remove_user_from_group', '/remove/{user}/from/{groupName}')
        ->controller([UserController::class, 'removeFromGroup'])
        ->methods(['POST']);

    $routes->add('emsco_add_user_to_group', '/add/{user}/to/{group}')
        ->controller([UserController::class, 'addToGroup'])
        ->methods(['GET']);

    $routes->add('emsco_user_enabling', '/enabling/{user}')
        ->controller([UserController::class, 'enabling'])
        ->methods(['POST']);

    $routes->add('emsco_user_api_key', '/api-key/{username}')
        ->controller([UserController::class, 'apiKey'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_user_spreadsheet_export', '/users.{_format}')
        ->controller([UserController::class, 'spreadsheetExport'])
        ->methods(['GET'])
        ->format('csv|xlsx');
};
