<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Admin\EntitiesController;
use EMS\CoreBundle\Controller\Api\Admin\InfoController;
use EMS\CoreBundle\Controller\Api\Admin\MetaController;
use EMS\CoreBundle\Controller\Api\JobApiController;
use EMS\CoreBundle\Controller\ContentManagement\JobController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_job_create', '/job/create')
        ->controller([JobApiController::class, 'create'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST']);
    $routes->add('emsco_api_job_status', '/job/{job}/status')
        ->controller([JobApiController::class, 'status'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_start_job', '/start-job/{job}')
        ->controller([JobController::class, 'startJob'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_start_next_job', '/next-job/{tag}')
        ->controller([JobController::class, 'startNextJob'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_job_completed', '/job-completed/{job}')
        ->controller([JobController::class, 'jobCompleted'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_job_failed', '/job-failed/{job}')
        ->controller([JobController::class, 'jobFailed'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_job_write', '/job-write/{job}')
        ->controller([JobController::class, 'jobWrite'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type', '/content-types')
        ->controller([MetaController::class, 'contentTypes'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_get_versions', '/versions')
        ->controller([InfoController::class, 'versions'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_config_types', '/config-types')
        ->controller([EntitiesController::class, 'getEntityNames'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type_index', '/{entity}')
        ->controller([EntitiesController::class, 'index'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type_create', '/{entity}')
        ->controller([EntitiesController::class, 'create'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type_get', '/{entity}/{name}')
        ->controller([EntitiesController::class, 'get'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type_update', '/{entity}/{name}')
        ->controller([EntitiesController::class, 'update'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_content_type_delete', '/{entity}/{name}')
        ->controller([EntitiesController::class, 'delete'])
        ->defaults(['_format' => 'json'])
        ->methods(['DELETE'])
        ->options(['openapi' => true]);
};
