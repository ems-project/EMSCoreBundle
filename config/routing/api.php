<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Admin\DocumentationController;
use EMS\CoreBundle\Controller\Api\AuthTokenLoginController;
use EMS\CoreBundle\Controller\Api\UserController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('emsco_auth_token_login', '/auth-token')
        ->controller([AuthTokenLoginController::class, 'login'])
        ->methods(['POST'])
        ->format('json');

    $routes->add('emsco_documentation_api', '/admin/documentation/api.{_format}')
        ->controller([DocumentationController::class, 'getDocumentation'])
        ->methods(['GET'])
        ->requirements(['_format' => 'html|json'])
        ->format('html');

    $routes->import('@EMSCoreBundle/config/routing/api/admin.php')
        ->prefix('/api/admin');

    $routes->import('@EMSCoreBundle/config/routing/api/extract-data.php')
        ->prefix('/api/extract-data');

    $routes->import('@EMSCoreBundle/config/routing/api/file.php')
        ->prefix('/api/file');

    $routes->import('@EMSCoreBundle/config/routing/api/forms.php')
        ->prefix('/api/forms');

    $routes->import('@EMSCoreBundle/config/routing/api/images.php')
        ->prefix('/api/images');

    $routes->import('@EMSCoreBundle/config/routing/api/meta.php')
        ->prefix('/api/meta');

    $routes->import('@EMSCoreBundle/config/routing/api/search.php')
        ->prefix('/api/search');

    $routes->import('@EMSCoreBundle/config/routing/api/data.php')
        ->prefix('/api/data');

    $routes->import('@EMSCoreBundle/config/routing/api/webhook.php')
        ->prefix('/api');

    $routes->add('emsco_api_user_proxy_authenticate', '/api/user/proxy-authenticate')
        ->controller([UserController::class, 'proxyAuthenticate'])
        ->methods(['POST'])
        ->options(['openapi' => true]);
};
