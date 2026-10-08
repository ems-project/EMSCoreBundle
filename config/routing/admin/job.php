<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\JobController;
use EMS\CoreBundle\Controller\Admin\JobScheduleController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_job_index', '/')
        ->controller([JobController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_job_add', '/add')
        ->controller([JobController::class, 'add'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_job_delete', '/delete/{job}')
        ->controller([JobController::class, 'delete'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('emsco_admin_job_relaunch', '/relaunch/{job}')
        ->controller([JobController::class, 'relaunch'])
        ->defaults(['_format' => 'html'])
        ->methods(['POST']);

    $routes->add('emsco_schedule_index', '/schedule')
        ->controller([JobScheduleController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_add', '/schedule/add')
        ->controller([JobScheduleController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_edit', '/schedule/edit/{schedule}.{_format}')
        ->controller([JobScheduleController::class, 'edit'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_schedule_duplicate', '/schedule/duplicate/{schedule}')
        ->controller([JobScheduleController::class, 'duplicate'])
        ->methods(['POST']);

    $routes->add('emsco_schedule_delete', '/schedule/delete/{schedule}')
        ->controller([JobScheduleController::class, 'delete'])
        ->methods(['POST']);
};
