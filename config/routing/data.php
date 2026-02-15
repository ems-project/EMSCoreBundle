<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\DataController;
use EMS\CoreBundle\Controller\ContentManagement\ReleaseController;
use EMS\CoreBundle\Controller\Revision\Action\ActionController;
use EMS\CoreBundle\Controller\Revision\Action\ActionImportController;
use EMS\CoreBundle\Controller\Revision\DetailController;
use EMS\CoreBundle\Controller\Revision\JsonMenuNestedController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Core data routes
    $routes->add('emsco_data_default_search', '/{name}')
        ->controller([DataController::class, 'root'])
        ->methods(['GET']);

    $routes->add('emsco_data_search_in_my_circles', '/in-my-circles/{name}')
        ->controller([DataController::class, 'inMyCircles'])
        ->methods(['GET']);

    $routes->add('emsco_data_view', '/view/{environmentName}/{type}/{ouuid}')
        ->controller([DataController::class, 'viewData'])
        ->methods(['GET']);

    $routes->add('emsco_data_revision_in_environment', '/revisions-in-environment/{environment}/{type}:{ouuid}')
        ->controller([DataController::class, 'revisionInEnvironmentData'])
        ->methods(['GET'])
        ->defaults(['deleted' => 0]);

    $routes->add('emsco_view_revisions', '/revisions/{type}:{ouuid}/{revisionId}/{compareId}')
        ->controller([DetailController::class, 'detailRevision'])
        ->methods(['GET'])
        ->defaults(['revisionId' => 0, 'compareId' => 0]);

    $routes->add('emsco_duplicate_revision', '/duplicate/{environment}/{type}/{ouuid}')
        ->controller([DataController::class, 'duplicate'])
        ->methods(['POST']);

    $routes->add('emsco_data_copy', '/copy/{environment}/{type}/{ouuid}')
        ->controller([DataController::class, 'copy'])
        ->methods(['GET']);

    $routes->add('emsco_data_new_draft', '/new-draft/{type}/{ouuid}')
        ->controller([DataController::class, 'newDraft'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_delete', '/delete/{type}/{ouuid}')
        ->controller([DataController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_discard_draft', '/draft/discard/{revisionId}')
        ->controller([DataController::class, 'discardRevision'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_data_cancel_modifications', '/cancel/{revision}')
        ->controller([DataController::class, 'cancelModifications'])
        ->methods(['POST']);

    $routes->add('emsco_data_reindex', '/revision/re-index/{revisionId}')
        ->controller([DataController::class, 'reindexRevision'])
        ->methods(['POST']);

    $routes->add('emsco_data_private_view', '/custom-index-view/{viewId}')
        ->controller([DataController::class, 'customIndexView'])
        ->methods(['GET', 'POST'])
        ->defaults(['public' => 0]);

    $routes->add('emsco_data_action_import', '/action/import/{actionId}/{ouuid}')
        ->controller(ActionImportController::class)
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_private_action', '/action/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller([ActionController::class, 'render'])
        ->methods(['GET'])
        ->defaults(['public' => 0, '_download' => 0]);

    $routes->add('emsco_job_custom_view', '/custom-view-job/{environmentName}/{templateId}/{ouuid}')
        ->controller([DataController::class, 'customViewJob'])
        ->methods(['POST']);

    $routes->add('emsco_data_ajax_update', '/revision/{revisionId}.json')
        ->controller([DataController::class, 'ajaxUpdate'])
        ->methods(['POST'])
        ->format('json');

    $routes->add('emsco_data_finalize', '/draft/finalize/{revision}')
        ->controller([DataController::class, 'finalizeDraft'])
        ->methods(['POST']);

    $routes->add('emsco_data_default_view', '/{type}')
        ->controller([DataController::class, 'root'])
        ->methods(['GET']);

    $routes->add('emsco_data_in_my_circle_view', '/in-my-circles/{name}')
        ->controller([DataController::class, 'inMyCircles'])
        ->methods(['GET']);

    $routes->add('emsco_data_duplicate_with_jsoncontent', '/duplicate-json/{contentType}/{ouuid}')
        ->controller([DataController::class, 'duplicateWithJsonContent'])
        ->methods(['POST']);

    $routes->add('emsco_data_add_from_jsoncontent', '/add-json/{contentType}')
        ->controller([DataController::class, 'addFromJsonContent'])
        ->methods(['POST']);

    $routes->add('emsco_data_add', '/add/{contentType}')
        ->controller([DataController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_revert', '/revisions/revert/{id}')
        ->controller([DataController::class, 'revertRevision'])
        ->methods(['POST']);

    $routes->add('emsco_data_link', '/link/{key}')
        ->controller([DataController::class, 'linkData'])
        ->methods(['GET']);

    // JSON Menu Nested routes
    $routes->add('emsco_data_json_menu_nested_modal_add', '/json-menu-nested/add/{revision}/{fieldType}')
        ->controller([JsonMenuNestedController::class, 'modal'])
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_modal_edit', '/json-menu-nested/edit/{revision}/{fieldType}')
        ->controller([JsonMenuNestedController::class, 'modal'])
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_paste', '/json-menu-nested/paste/{revision}/{fieldType}')
        ->controller([JsonMenuNestedController::class, 'paste'])
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_modal_preview', '/json-menu-nested/preview/{parentFieldType}')
        ->controller([JsonMenuNestedController::class, 'modalPreview'])
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_silent_publish', '/json-menu-nested/silent-publish/{revision}/{fieldType}/{field}')
        ->controller([JsonMenuNestedController::class, 'silentPublish'])
        ->methods(['POST']);

    // Release routes
    $routes->add('emsco_pick_a_release', '/pick-a-release/{revision}')
        ->controller([ReleaseController::class, 'pickRelease'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_add_revision_to_release', '/add-to-release/{type}/{revision}/{release}')
        ->controller([ReleaseController::class, 'addRevisionById'])
        ->methods(['POST'])
        ->requirements(['type' => 'publish|unpublish']);
};
