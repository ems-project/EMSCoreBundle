<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Admin\WysiwygController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_wysiwyg_index', '/')
        ->controller([WysiwygController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_profile_add', '/profile/add')
        ->controller([WysiwygController::class, 'profileAdd'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_profile_edit', '/profile/{wysiwygProfile}/edit')
        ->controller([WysiwygController::class, 'profileEdit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_profile_delete', '/profile/{wysiwygProfile}/delete')
        ->controller([WysiwygController::class, 'profileDelete'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_style_set_new', '/styles-set/add')
        ->controller([WysiwygController::class, 'styleSetAdd'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_style_set_edit', '/styles-set/{wysiwygStyleSet}/edit')
        ->controller([WysiwygController::class, 'styleSetEdit'])
        ->methods(['GET', 'POST']);

    $routes->add('emsco_wysiwyg_style_set_delete', '/styles-set/{wysiwygStyleSet}/delete')
        ->controller([WysiwygController::class, 'styleSetDelete'])
        ->methods(['POST']);
};
