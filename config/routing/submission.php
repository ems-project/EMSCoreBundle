<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Form\SubmissionController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('form.submissions', '/')
        ->controller([SubmissionController::class, 'index'])
        ->methods(['GET', 'POST']);

    $routes->add('form.submissions.process', '/process/{formSubmission}')
        ->controller([SubmissionController::class, 'process'])
        ->methods(['POST']);

    $routes->add('form.submissions.download', '/download/{formSubmission}')
        ->controller([SubmissionController::class, 'download'])
        ->methods(['GET'])
        ->requirements(['id' => '\S+']);

    $routes->add('form.submissions.attachments.download', '/attachments/{formSubmission}/{filename}')
        ->controller([SubmissionController::class, 'downloadAttachment'])
        ->methods(['GET']);
};
