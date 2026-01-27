<?php

declare(strict_types=1);

use EMS\CoreBundle\Controller\Api\Form\SubmissionController;
use EMS\CoreBundle\Controller\Api\Form\VerificationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('emsco_api_forms_submissions', '/submissions')
        ->controller([SubmissionController::class, 'submit'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_forms_submissions_file', '/submissions/{submissionId}/files/{submissionFileId}')
        ->controller([SubmissionController::class, 'submissionFile'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true])
        ->requirements([
            'submissionId' => '.*',
            'submissionFileId' => '.*',
        ]);

    $routes->add('emsco_api_forms_submissions_detail', '/submissions/{submissionId}')
        ->controller([SubmissionController::class, 'submission'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET'])
        ->options(['openapi' => true])
        ->requirements(['submissionId' => '.*']);

    $routes->add('emsco_api_forms_post_verifications', '/verifications')
        ->controller([VerificationController::class, 'createVerification'])
        ->defaults(['_format' => 'json'])
        ->methods(['POST'])
        ->options(['openapi' => true]);

    $routes->add('emsco_api_forms_get_verifications', '/verifications')
        ->controller([VerificationController::class, 'getVerification'])
        ->defaults(['_format' => 'json'])
        ->methods(['GET']);
};
