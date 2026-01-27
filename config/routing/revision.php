<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\PublishController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('revision.unpublish', '/unpublish/{revisionId}/{envId}')
        ->controller([PublishController::class, 'unPublish'])
        ->methods(['GET', 'POST']);
};
