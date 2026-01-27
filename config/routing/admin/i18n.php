<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\I18nController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_i18n_index', '/')
        ->controller([I18nController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_i18n_add', '/add')
        ->controller([I18nController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_i18n_edit', '/{i18n}/edit')
        ->controller([I18nController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_i18n_delete', '/{i18n}/delete')
        ->controller([I18nController::class, 'delete'])
        ->methods(['POST']);
};
