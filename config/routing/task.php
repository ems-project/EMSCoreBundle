<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Revision\TaskController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_task_ajax_tasks', '/data/revisions/{revisionOuuid}/tasks')
        ->controller([TaskController::class, 'ajaxGetTasks'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_task_ajax_modal_task', '/data/revisions/{revisionOuuid}/task-modal/{taskId}')
        ->controller([TaskController::class, 'ajaxModalTask'])
        ->methods(['GET']);

    $routes->add('emsco_task_ajax_modal_create', '/data/revisions/{revisionOuuid}/create-modal')
        ->controller([TaskController::class, 'ajaxModalCreate'])
        ->methods(['GET', 'POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_task_ajax_modal_update', '/data/revisions/{revisionOuuid}/update-modal/{taskId}')
        ->controller([TaskController::class, 'ajaxModalUpdate'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_task_ajax_delete', '/data/revisions/{revisionOuuid}/delete/{taskId}')
        ->controller([TaskController::class, 'ajaxModalDelete'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_task_ajax_reorder', '/data/revisions/{revisionOuuid}/reorder')
        ->controller([TaskController::class, 'ajaxReorder'])
        ->methods(['POST']);
};
