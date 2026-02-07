<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\JobController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_job_status', '/status/{job}.{_format}')
        ->controller([JobController::class, 'jobStatus'])
        ->methods(['GET'])
        ->format('html')
        ->requirements([
            '_format' => 'html|json',
        ]);

    $routes->add('emsco_job_start', '/start/{job}')
        ->controller([JobController::class, 'startJob'])
        ->methods(['POST'])
        ->format('html');
};
