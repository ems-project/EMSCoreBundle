<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Webhook\WebhookController;
use EMS\CoreBundle\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add(Routes::WEBHOOK_SUBSCRIPTION_INDEX, '/')
        ->controller([WebhookController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add(Routes::WEBHOOK_SUBSCRIPTION_DELETE, '/delete/{webhookSubscription}')
        ->controller([WebhookController::class, 'delete'])
        ->methods(['POST']);

    $routes->add(Routes::WEBHOOK_SUBSCRIPTION_TEST, '/test/{webhookSubscription}')
        ->controller([WebhookController::class, 'test'])
        ->methods(['POST']);

    $routes->add(Routes::WEBHOOK_SUBSCRIPTION_TOGGLE_ENABLE, '/toggle-enable/{webhookSubscription}')
        ->controller([WebhookController::class, 'toggleEnable'])
        ->methods(['POST']);
};
