<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\ViewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_admin_content_type_view_index', '/{contentType}')
        ->controller([ViewController::class, 'index'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_view_add', '/add/{contentType}')
        ->controller([ViewController::class, 'add'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_view_edit', '/edit/{view}.{_format}')
        ->controller([ViewController::class, 'edit'])
        ->defaults(['_format' => 'html'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_admin_content_type_view_duplicate', '/duplicate/{view}')
        ->controller([ViewController::class, 'duplicate'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_view_delete', '/delete/{view}')
        ->controller([ViewController::class, 'delete'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_view_define', '/{view}/define/{definition}')
        ->controller([ViewController::class, 'define'])
        ->methods(['POST']);

    $routes->add('emsco_admin_content_type_view_undefine', '/{view}/undefine')
        ->controller([ViewController::class, 'undefine'])
        ->methods(['POST']);
};
