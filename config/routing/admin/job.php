<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\ScheduleController;
use EMS\CoreBundle\Controller\ContentManagement\JobController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_job_index', '/')
        ->controller([JobController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_job_add', '/add')
        ->controller([JobController::class, 'create'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_job_delete', '/delete/{job}')
        ->controller([JobController::class, 'delete'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('emsco_job_relaunch', '/relaunch/{job}')
        ->controller([JobController::class, 'relaunch'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('emsco_job_admin_start', '/start/{job}')
        ->controller([JobController::class, 'startJob'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('emsco_schedule_index', '/schedule')
        ->controller([ScheduleController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_add', '/schedule/add')
        ->controller([ScheduleController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_edit', '/schedule/edit/{schedule}.{_format}')
        ->controller([ScheduleController::class, 'edit'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_duplicate', '/schedule/duplicate/{schedule}')
        ->controller([ScheduleController::class, 'duplicate'])
        ->methods(['POST']);

    $routes->add('emsco_schedule_delete', '/schedule/delete/{schedule}')
        ->controller([ScheduleController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('job.index', '/')
        ->controller([JobController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET']);

    $routes->add('job.add', '/add')
        ->controller([JobController::class, 'create'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('job.delete', '/delete/{job}')
        ->controller([JobController::class, 'delete'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('job.start', '/start/{job}')
        ->controller([JobController::class, 'startJob'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);
};
