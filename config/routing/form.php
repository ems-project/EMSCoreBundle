<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Form\FormController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_form_admin_index', '/admin/form')
        ->controller([FormController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_form_admin_add', '/admin/form/add')
        ->controller([FormController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_form_admin_edit', '/admin/form/edit/{form}')
        ->controller([FormController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_form_admin_delete', '/admin/form/delete/{form}')
        ->controller([FormController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_form_admin_reorder', '/admin/form/reorder/{form}')
        ->controller([FormController::class, 'reorder'])
        ->methods(['GET', 'POST']);
};
