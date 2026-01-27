<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\FileController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('core-favicon', 'favicon.ico')
        ->controller([FileController::class, 'icon'])
        ->methods(['GET', 'HEAD'])
        ->defaults([
            'width' => 128,
            'height' => 128,
        ]);

    $routes->add('core-png-icon', '{name}-{width}x{height}.png')
        ->controller([FileController::class, 'icon'])
        ->methods(['GET', 'HEAD'])
        ->requirements([
            'width' => '16|32|48|64|128|150|192|256|512',
            'height' => '16|32|48|64|128|150|192|256|512',
            'name' => 'favicon|android\-chrome|mstile',
        ]);

    $routes->add('core-apple-touch-icon', 'apple-touch-icon.png')
        ->controller([FileController::class, 'icon'])
        ->methods(['GET', 'HEAD'])
        ->defaults([
            'width' => 180,
            'height' => 180,
        ]);

    $routes->add('core-browserconfig', 'browserconfig.xml')
        ->controller([FileController::class, 'browserConfig'])
        ->methods(['GET', 'HEAD'])
        ->defaults([
            '_format' => 'xml',
        ]);

    $routes->add('core-site-webmanifest', 'site.webmanifest')
        ->controller([FileController::class, 'webManifest'])
        ->methods(['GET', 'HEAD'])
        ->defaults([
            '_format' => 'webmanifest',
        ]);
};
