<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\DatatableController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_datatable_ajax_table', '/ajax/table/{hash}/{optionsCacheKey}')
        ->controller([DatatableController::class, 'ajaxData'])
        ->methods(['GET', 'POST'])
        ->defaults(['optionsCacheKey' => null]);

    $routes->add('emsco_datatable_ajax_table_export', '/ajax/table/export/{format}/{hash}/{optionsCacheKey}')
        ->controller([DatatableController::class, 'ajaxExport'])
        ->methods(['GET'])
        ->requirements(['format' => 'excel|csv'])
        ->defaults(['optionsCacheKey' => null]);

    $routes->add('ems_core_datatable_ajax_elastica', '/ajax/{hashConfig}.json')
        ->controller([DatatableController::class, 'ajaxElastica'])
        ->methods(['GET', 'HEAD', 'POST']);

    $routes->add('ems_core_datatable_excel_elastica', '/excel/{hashConfig}')
        ->controller([DatatableController::class, 'excelElastica'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_core_datatable_csv_elastica', '/csv/{hashConfig}.csv')
        ->controller([DatatableController::class, 'csvElastica'])
        ->methods(['GET', 'HEAD']);
};
