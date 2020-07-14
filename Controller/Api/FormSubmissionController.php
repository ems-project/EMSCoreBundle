<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Service\FormSubmission\FormSubmissionException;
use EMS\CoreBundle\Service\FormSubmission\FormSubmissionService;
use EMS\CoreBundle\Service\FormSubmission\SubmitRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FormSubmissionController extends AbstractController
{
    /** @var FormSubmissionService */
    private $formSubmissionService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(FormSubmissionService $formSubmissionService, LoggerInterface $logger)
    {
        $this->formSubmissionService = $formSubmissionService;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/form-submission", defaults={"_format": "json"}, methods={"POST"})
     */
    public function submit(Request $request): Response
    {
        try {
            $submitRequest = new SubmitRequest($request);

            return new JsonResponse($this->formSubmissionService->submit($submitRequest));
        } catch (FormSubmissionException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
