<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Core data routes
    $routes->add('emsco_data_default_search', '/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::root')
        ->methods(['GET']);

    $routes->add('emsco_data_search_in_my_circles', '/in-my-circles/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::inMyCircles')
        ->methods(['GET']);

    $routes->add('emsco_data_view', '/view/{environmentName}/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::viewData')
        ->methods(['GET']);

    $routes->add('emsco_data_revision_in_environment', '/revisions-in-environment/{environment}/{type}:{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::revisionInEnvironmentData')
        ->methods(['GET'])
        ->defaults(['deleted' => 0]);

    $routes->add('emsco_view_revisions', '/revisions/{type}:{ouuid}/{revisionId}/{compareId}')
        ->controller('EMS\CoreBundle\Controller\Revision\DetailController::detailRevision')
        ->methods(['GET'])
        ->defaults(['revisionId' => 0, 'compareId' => 0]);

    $routes->add('emsco_duplicate_revision', '/duplicate/{environment}/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::duplicate')
        ->methods(['POST']);

    $routes->add('emsco_data_copy', '/copy/{environment}/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::copy')
        ->methods(['GET']);

    $routes->add('emsco_data_new_draft', '/new-draft/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::newDraft')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_delete', '/delete/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::delete')
        ->methods(['POST']);

    $routes->add('emsco_discard_draft', '/draft/discard/{revisionId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::discardRevision')
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_data_cancel_modifications', '/cancel/{revision}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::cancelModifications')
        ->methods(['POST']);

    $routes->add('emsco_data_reindex', '/revision/re-index/{revisionId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::reindexRevision')
        ->methods(['POST']);

    $routes->add('emsco_data_private_view', '/custom-index-view/{viewId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::customIndexView')
        ->methods(['GET', 'POST'])
        ->defaults(['public' => 0]);

    $routes->add('emsco_data_action_import', '/action/import/{actionId}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\Revision\Action\ActionImportController')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_private_action', '/action/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller('EMS\CoreBundle\Controller\Revision\Action\ActionController::render')
        ->methods(['GET'])
        ->defaults(['public' => 0, '_download' => 0]);

    $routes->add('emsco_job_custom_view', '/custom-view-job/{environmentName}/{templateId}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::customViewJob')
        ->methods(['POST']);

    $routes->add('emsco_data_ajax_update', '/revision/{revisionId}.json')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::ajaxUpdate')
        ->methods(['POST'])
        ->format('json');

    $routes->add('emsco_data_finalize', '/draft/finalize/{revision}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::finalizeDraft')
        ->methods(['POST']);

    $routes->add('emsco_data_default_view', '/{type}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::root')
        ->methods(['GET']);

    $routes->add('emsco_data_in_my_circle_view', '/in-my-circles/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::inMyCircles')
        ->methods(['GET']);

    $routes->add('emsco_data_duplicate_with_jsoncontent', '/duplicate-json/{contentType}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::duplicateWithJsonContent')
        ->methods(['POST']);

    $routes->add('emsco_data_add_from_jsoncontent', '/add-json/{contentType}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::addFromJsonContent')
        ->methods(['POST']);

    $routes->add('emsco_data_add', '/add/{contentType}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::add')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_revert', '/revisions/revert/{id}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::revertRevision')
        ->methods(['POST']);

    $routes->add('emsco_data_link', '/link/{key}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::linkData')
        ->methods(['GET']);

    // JSON Menu Nested routes
    $routes->add('emsco_data_json_menu_nested_modal_add', '/json-menu-nested/add/{revision}/{fieldType}')
        ->controller('EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::modal')
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_modal_edit', '/json-menu-nested/edit/{revision}/{fieldType}')
        ->controller('EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::modal')
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_paste', '/json-menu-nested/paste/{revision}/{fieldType}')
        ->controller('EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::paste')
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_modal_preview', '/json-menu-nested/preview/{parentFieldType}')
        ->controller('EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::modalPreview')
        ->methods(['POST']);

    $routes->add('emsco_data_json_menu_nested_silent_publish', '/json-menu-nested/silent-publish/{revision}/{fieldType}/{field}')
        ->controller('EMS\CoreBundle\Controller\Revision\JsonMenuNestedController::silentPublish')
        ->methods(['POST']);

    // Release routes
    $routes->add('emsco_pick_a_release', '/pick-a-release/{revision}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\ReleaseController::pickRelease')
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_add_revision_to_release', '/add-to-release/{type}/{revision}/{release}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\ReleaseController::addRevisionById')
        ->methods(['POST'])
        ->requirements(['type' => 'publish|unpublish']);

    // Deprecated routes
    $routes->add('ems_data_default_search', '/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::root')
        ->methods(['GET']);

    $routes->add('data.root', '/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::root')
        ->methods(['GET']);

    $routes->add('ems_search_in_my_circles', '/in-my-circles/{name}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::inMyCircles')
        ->methods(['GET']);

    $routes->add('data.view', '/view/{environmentName}/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::viewData')
        ->methods(['GET']);

    $routes->add('data.revision_in_environment', '/revisions-in-environment/{environment}/{type}:{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::revisionInEnvironmentData')
        ->methods(['GET'])
        ->defaults(['deleted' => 0]);

    $routes->add('ems_content_revisions_view', '/revisions/{type}:{ouuid}/{revisionId}/{compareId}')
        ->controller('EMS\CoreBundle\Controller\Revision\DetailController::detailRevision')
        ->methods(['GET'])
        ->defaults(['revisionId' => 0, 'compareId' => 0]);

    $routes->add('data.revisions', '/revisions/{type}:{ouuid}/{revisionId}/{compareId}')
        ->controller('EMS\CoreBundle\Controller\Revision\DetailController::detailRevision')
        ->methods(['GET'])
        ->defaults(['revisionId' => 0, 'compareId' => 0]);

    $routes->add('revision.copy', '/copy/{environment}/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::copy')
        ->methods(['GET']);

    $routes->add('revision.new-draft', '/new-draft/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::newDraft')
        ->methods(['POST']);

    $routes->add('object.delete', '/delete/{type}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::delete')
        ->methods(['POST']);

    $routes->add('revision.discard', '/draft/discard/{revisionId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::discardRevision')
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('revision.cancel', '/cancel/{revision}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::cancelModifications')
        ->methods(['POST']);

    $routes->add('revision.reindex', '/revision/re-index/{revisionId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::reindexRevision')
        ->methods(['POST']);

    $routes->add('data.customindexview', '/custom-index-view/{viewId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::customIndexView')
        ->methods(['GET'])
        ->defaults(['public' => 0]);

    $routes->add('ems_custom_view_protected', '/custom-index-view/{viewId}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::customIndexView')
        ->methods(['GET'])
        ->defaults(['public' => 0]);

    $routes->add('data.customview', '/custom-view/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller('EMS\CoreBundle\Controller\Revision\Action\ActionController::render')
        ->methods(['GET'])
        ->defaults(['public' => 0, '_download' => 0]);

    $routes->add('ems_data_custom_template_protected', '/template/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller('EMS\CoreBundle\Controller\Revision\Action\ActionController::render')
        ->methods(['GET'])
        ->defaults(['public' => 0, '_download' => 0]);

    $routes->add('ems_job_custom_view', '/custom-view-job/{environmentName}/{templateId}/{ouuid}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::customViewJob')
        ->methods(['POST']);

    $routes->add('revision.ajaxupdate', '/revision/{revisionId}.json')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::ajaxUpdate')
        ->methods(['POST'])
        ->defaults(['_format' => 'json']);

    $routes->add('revision.finalize', '/draft/finalize/{revision}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::finalizeDraft')
        ->methods(['POST']);

    $routes->add('data.add', '/add/{contentType}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::add')
        ->methods(['GET', 'POST']);

    $routes->add('revision.revert', '/revisions/revert/{id}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::revertRevision')
        ->methods(['POST']);

    $routes->add('data.link', '/link/{key}')
        ->controller('EMS\CoreBundle\Controller\ContentManagement\DataController::linkData')
        ->methods(['GET']);
};
