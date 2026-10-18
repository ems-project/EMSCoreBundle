<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\ChoiceListController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_choice_list_admin_index', '/admin/choice-list')
        ->controller([ChoiceListController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_choice_list_admin_add', '/admin/choice-list/add')
        ->controller([ChoiceListController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_choice_list_admin_edit', '/admin/choice-list/edit/{choiceList}')
        ->controller([ChoiceListController::class, 'edit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_choice_list_admin_delete', '/admin/choice-list/delete/{choiceList}')
        ->controller([ChoiceListController::class, 'delete'])
        ->methods(['POST']);
};
