<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Data\PublishController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_data_publish', '/{contentTypeName}/publish/{ouuid}/{targetEnvironmentName}/{revision}')
        ->controller([PublishController::class, 'publish'])
        ->defaults(['_format' => 'json', 'revision' => null])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_data_publish_versions', '/{contentTypeName}/publish-versions/{versionUuid}/{environment}')
        ->controller([PublishController::class, 'publishVersions'])
        ->defaults(['_format' => 'json', 'revision' => null])
        ->methods(['POST'])
        ->options(['openapi' => true]);
};
