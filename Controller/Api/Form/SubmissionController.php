<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CoreBundle\Service\Form\Submission\FormSubmissionException;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SubmissionController extends AbstractController
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
     * @Route("/api/forms/submissions", defaults={"_format": "json"}, methods={"POST"})
     */
    public function submit(Request $request): Response
    {
        try {
            return new JsonResponse($this->formSubmissionService->submit(new FormSubmissionRequest($request)));
        } catch (FormSubmissionException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
