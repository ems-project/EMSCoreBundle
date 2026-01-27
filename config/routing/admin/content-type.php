<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\ContentTypeController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_content_type_update_from_json', '/json-update/{contentType}')
        ->controller([ContentTypeController::class, 'updateFromJson'])
        ->methods(['GET', 'POST'])
        ->format('html');

    $routes->add('emsco_admin_content_type_remove', '/remove/{contentType}')
        ->controller([ContentTypeController::class, 'remove'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_activate', '/activate/{contentType}')
        ->controller([ContentTypeController::class, 'activate'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_deactivate', '/disable/{contentType}')
        ->controller([ContentTypeController::class, 'disable'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_refresh_mapping', '/refresh-mapping/{contentType}')
        ->controller([ContentTypeController::class, 'refreshMapping'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_add', '/add')
        ->controller([ContentTypeController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_add_referenced_index', '/add-referenced')
        ->controller([ContentTypeController::class, 'addReferencedIndex'])
        ->methods(['GET']);

    $routes->add('emsco_admin_content_type_add_referenced', '/add-referenced/{environment}/{name}')
        ->controller([ContentTypeController::class, 'addReferenced'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_index', '/')
        ->controller([ContentTypeController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_edit_field', '/{contentType}/field/{field}')
        ->controller([ContentTypeController::class, 'editField'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_reorder', '/reorder/{contentType}')
        ->controller([ContentTypeController::class, 'reorder'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_edit', '/{contentType}')
        ->controller([ContentTypeController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_structure', '/structure/{id}')
        ->controller([ContentTypeController::class, 'editStructure'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_export', '/export/{contentType}.{_format}')
        ->controller([ContentTypeController::class, 'export'])
        ->methods(['GET'])
        ->format('json');
};
