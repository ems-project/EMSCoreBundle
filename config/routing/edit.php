<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Revision\EditController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_edit_revision', '/draft/edit/{revisionId}')
        ->controller([EditController::class, 'editRevision'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_edit_json_revision', '/draft/edit-json/{revision}')
        ->controller([EditController::class, 'editJsonRevision'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_draft_in_progress', '/draft/{contentTypeId}')
        ->controller([EditController::class, 'draftInProgress'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_revision_archive', '/archive/{revision}')
        ->controller([EditController::class, 'archiveRevision'])
        ->methods(['POST']);
};
