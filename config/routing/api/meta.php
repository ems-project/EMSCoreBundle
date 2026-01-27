<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Admin\MetaController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_meta_drafts', '/drafts')
        ->controller([MetaController::class, 'drafts'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_meta_environments', '/environments')
        ->controller([MetaController::class, 'environments'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_meta_alias_attach_environment', '/alias-attach-environment')
        ->controller([MetaController::class, 'aliasAttachEnvironment'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_meta_info_documents', '/info/documents')
        ->controller([MetaController::class, 'infoDocuments'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_meta_content_type', '/content-type/{contentTypeName}')
        ->controller([MetaController::class, 'contentType'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true]);
};
