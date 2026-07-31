<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\BrowseController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add(Routes::BROWSE_UPLOADED_FILES, '/uploaded-files')
        ->controller([BrowseController::class, 'modalUploadedFiles'])
        ->methods(['GET'])
        ->format('json')
    ;
    $routes->add(Routes::BROWSE_DASHBOARD, '/dashboard/{dashboardName}')
        ->controller([BrowseController::class, 'modalDashboard'])
        ->methods(['GET'])
        ->requirements(['dashboardName' => '.+'])
        ->format('json')
    ;
};
