<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Views\CalendarController;
use EMS\CoreBundle\Controller\Views\CriteriaController;
use EMS\CoreBundle\Controller\Views\HierarchicalController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_views_hierarchical_item', '/hierarchical/item/{view}/{key}')
        ->controller([HierarchicalController::class, 'item'])
        ->methods(['GET']);
    $routes->add('emsco_views_calendar_replan', '/calendar/replan/{view}.json')
        ->controller([CalendarController::class, 'update'])
        ->methods(['POST'])
        ->format('json');
    $routes->add('emsco_views_calendar_search', '/calendar/search/{view}.json')
        ->controller([CalendarController::class, 'update'])
        ->methods(['GET'])
        ->format('json');
    $routes->add('emsco_views_criteria_align', '/criteria/align/{view}')
        ->controller([CriteriaController::class, 'align'])
        ->methods(['POST']);
    $routes->add('emsco_views_criteria_table', '/criteria/table/{view}')
        ->controller([CriteriaController::class, 'generateCriteriaTable'])
        ->methods(['GET', 'POST']);
    $routes->add('emsco_views_criteria_add', '/criteria/addCriterion/{view}')
        ->controller([CriteriaController::class, 'addCriteria'])
        ->methods(['POST']);
    $routes->add('emsco_views_criteria_remove', '/criteria/removeCriterion/{view}')
        ->controller([CriteriaController::class, 'removeCriteria'])
        ->methods(['POST']);
    $routes->add('emsco_views_criteria_fieldFilter', '/criteria/fieldFilter')
        ->controller([CriteriaController::class, 'fieldFilter'])
        ->methods(['GET']);

    $routes->add('views.hierarchical.item', '/hierarchical/item/{view}/{key}')
        ->controller([HierarchicalController::class, 'item'])
        ->methods(['GET']);
    $routes->add('views.calendar.replan', '/calendar/replan/{view}.json')
        ->controller([CalendarController::class, 'update'])
        ->methods(['POST'])
        ->format('json');
    $routes->add('views.calendar.search', '/calendar/search/{view}.json')
        ->controller([CalendarController::class, 'update'])
        ->methods(['GET'])
        ->format('json');
    $routes->add('views.criteria.align', '/criteria/align/{view}')
        ->controller([CriteriaController::class, 'align'])
        ->methods(['POST']);
    $routes->add('views.criteria.table', '/criteria/table/{view}')
        ->controller([CriteriaController::class, 'generateCriteriaTable'])
        ->methods(['GET', 'POST']);
    $routes->add('views.criteria.add', '/criteria/addCriterion/{view}')
        ->controller([CriteriaController::class, 'addCriteria'])
        ->methods(['POST']);
    $routes->add('views.criteria.remove', '/criteria/removeCriterion/{view}')
        ->controller([CriteriaController::class, 'removeCriteria'])
        ->methods(['POST']);
    $routes->add('views.criteria.fieldFilter', '/criteria/fieldFilter')
        ->controller([CriteriaController::class, 'fieldFilter'])
        ->methods(['GET']);
};
