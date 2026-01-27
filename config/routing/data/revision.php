<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Revision\TrashController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_data_trash', '/trash/{contentType}')
        ->controller([TrashController::class, 'trash'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_data_put_back', '/put-back/{contentType}/{ouuid}')
        ->controller([TrashController::class, 'putBack'])
        ->methods(['POST']);

    $routes->add('emsco_data_empty_trash', '/empty-trash/{contentType}/{ouuid}')
        ->controller([TrashController::class, 'emptyTrash'])
        ->methods(['POST']);

    // deprecated routes
    $routes->add('ems_data_trash', '/trash/{contentType}')
        ->controller([TrashController::class, 'trash'])
        ->methods(['GET']);

    $routes->add('ems_data_put_back', '/put-back/{contentType}/{ouuid}')
        ->controller([TrashController::class, 'putBack'])
        ->methods(['POST']);

    $routes->add('ems_data_empty_trash', '/empty-trash/{contentType}/{ouuid}')
        ->controller([TrashController::class, 'emptyTrash'])
        ->methods(['POST']);
};
