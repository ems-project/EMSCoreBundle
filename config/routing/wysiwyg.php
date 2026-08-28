<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Wysiwyg\AjaxPasteController;
use EMS\CoreBundle\Controller\Wysiwyg\StylesetController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    // Styleset
    $routes->add('emsco_wysiwyg_styleset_iframe', '/styleset/iframe/{name}/{language}')
        ->controller([StylesetController::class, 'iframe'])
        ->methods(['GET']);

    $routes->add('emsco_wysiwyg_styleset_prefixed_css', '/styleset/prefixed-css/{name}.css')
        ->controller([StylesetController::class, 'prefixedCSS'])
        ->methods(['GET']);

    $routes->add('emsco_wysiwyg_styleset_all_prefixed_css', '/styleset/all-prefixed-css.css')
        ->controller([StylesetController::class, 'allPrefixedCSS'])
        ->methods(['GET']);

    // AJAX Paste
    $routes->add('emsco_wysiwyg_ajax_paste', '/ajax/{wysiwygProfileId}/paste')
        ->controller([AjaxPasteController::class, '__invoke'])
        ->methods(['POST']);
};
