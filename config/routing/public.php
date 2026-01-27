<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\DataController;
use EMS\CoreBundle\Controller\ContentManagement\DatatableController;
use EMS\CoreBundle\Controller\ContentManagement\FileController;
use EMS\CoreBundle\Controller\Revision\Action\ActionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Public data views
    $routes->add('emsco_data_public_view', '/public/view/{viewId}')
        ->controller([DataController::class, 'customIndexView'])
        ->methods(['GET'])
        ->defaults(['public' => 1]);

    $routes->add('emsco_data_public_action', '/public/action/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller([ActionController::class, 'render'])
        ->methods(['GET'])
        ->defaults([
            'public' => 1,
            '_download' => 0,
        ]);

    $routes->add('ems_file_download_public', '/public/file/{sha1}')
        ->controller([FileController::class, 'downloadFile'])
        ->methods(['GET', 'HEAD']);

    // Deprecated routes
    $routes->add('ems_custom_view_public', '/public/view/{viewId}')
        ->controller([DataController::class, 'customIndexView'])
        ->methods(['GET'])
        ->defaults(['public' => 1]);

    $routes->add('ems_data_custom_template_public', '/public/template/{environmentName}/{templateId}/{ouuid}/{_download}')
        ->controller([ActionController::class, 'render'])
        ->methods(['GET'])
        ->defaults([
            'public' => 1,
            '_download' => 0,
        ]);

    // Public datatable
    $routes->add('ems_core_datatable_ajax_elastica_public', '/public/datatable/ajax/{hashConfig}.json')
        ->controller([DatatableController::class, 'ajaxElastica'])
        ->methods(['GET', 'HEAD', 'POST']);

    $routes->add('ems_core_datatable_excel_elastica_public', '/public/datatable/excel/{hashConfig}')
        ->controller([DatatableController::class, 'excelElastica'])
        ->methods(['GET', 'HEAD']);

    $routes->add('ems_core_datatable_csv_elastica_public', '/public/datatable/csv/{hashConfig}.csv')
        ->controller([DatatableController::class, 'csvElastica'])
        ->methods(['GET', 'HEAD']);
};
