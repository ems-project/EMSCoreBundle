<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\ReleaseController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_release_index', '/')
        ->controller([ReleaseController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_release_add', '/add')
        ->controller([ReleaseController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_release_edit', '/edit/{release}')
        ->controller([ReleaseController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_release_view', '/view/{release}')
        ->controller([ReleaseController::class, 'view'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_release_delete', '/delete/{release}')
        ->controller([ReleaseController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_release_set_status', '/{release}/set-status/{status}')
        ->controller([ReleaseController::class, 'changeStatus'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_release_add_revision', '/{release}/add-revision/{type}/{emsLinkToAdd}')
        ->controller([ReleaseController::class, 'addRevision'])
        ->methods(['POST'])
        ->requirements(['type' => 'publish|unpublish']);

    $routes->add('emsco_release_add_revisions', '/{release}/add-revisions/{type}')
        ->controller([ReleaseController::class, 'addRevisions'])
        ->methods(['GET', 'POST'])
        ->requirements(['type' => 'publish|unpublish']);

    $routes->add('emsco_release_publish', '/{release}/publish')
        ->controller([ReleaseController::class, 'releasePublish'])
        ->methods(['POST']);
};
