<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\WebhookSubscriptionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_webhook_subscribe', '/webhook-subscriptions')
        ->controller([WebhookSubscriptionController::class, 'subscribe'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST']);

    $routes->add('emsco_api_webhook_unsubscribe', '/webhook-subscriptions/{id}')
        ->controller([WebhookSubscriptionController::class, 'unsubscribe'])
        ->defaults(['_format' => 'json'])
        ->methods(['DELETE']);
};
