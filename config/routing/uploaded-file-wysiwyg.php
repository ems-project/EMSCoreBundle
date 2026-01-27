<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\UploadedFileWysiwygController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('ems_core_uploaded_file_wysiwyg_index', '/')
        ->controller([UploadedFileWysiwygController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('ems_core_uploaded_file_wysiwyg_modal', '/modal')
        ->controller([UploadedFileWysiwygController::class, 'modal'])
        ->methods(['GET', 'POST']);
};
