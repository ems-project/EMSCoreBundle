<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\ActionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_action_field', '/action/revision/{revisionId}/field/{fieldId}')
        ->controller([ActionController::class, 'revisionField'])
        ->methods(['GET'])
        ->format('json')
        ->requirements([
            'revisionId' => '\d+',
            'fieldId' => '\d+',
        ]);
};
