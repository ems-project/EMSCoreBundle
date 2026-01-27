<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\DataController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_public_key', '/public-key')
        ->controller([DataController::class, 'publicKey'])
        ->methods(['GET']);

    // Duplicate route ID, kept as in XML
    $routes->add('ems_get_public_key', '/public-key')
        ->controller([DataController::class, 'publicKey'])
        ->methods(['GET']);
};
