<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ContentManagement\PublishController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('revision.publish_to', '/to/{revisionId}/{envId}')
        ->controller([PublishController::class, 'publishTo'])
        ->methods(['GET', 'POST']);

    $routes->add('search.publish', '/search-result')
        ->controller([PublishController::class, 'publishSearchResult'])
        ->methods(['GET', 'POST'])
        ->defaults([
            'deleted' => 0,
            'managed' => 1,
        ]);
};
