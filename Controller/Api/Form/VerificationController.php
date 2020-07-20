<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CoreBundle\Service\FormSubmission\FormSubmissionException;
use EMS\CoreBundle\Service\FormSubmission\FormSubmissionService;
use EMS\CoreBundle\Service\FormSubmission\SubmitRequest;
use EMS\CoreBundle\Service\FormVerification\FormVerificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/form/verification")
 */
final class VerificationController extends AbstractController
{
    /** @var FormVerificationService */
    private $formVerificationService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->formVerificationService = new FormVerificationService();
        $this->logger = $logger;
    }

    /**
     * @Route("", defaults={"_format": "json"}, methods={"POST"})
     */
    public function generate(Request $request): Response
    {
        try {
            $json = \json_decode($request->getContent(), true);
            $code = $this->formVerificationService->generateCode($json['value']);

            return new JsonResponse(['code' => $code]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/{value}/{code}", requirements={"value"=".+", "code"=".+"}, defaults={"_format": "json"}, methods={"GET"})
     */
    public function verify(string $value, string $code): Response
    {
        try {
            return new JsonResponse(['valid' => $this->formVerificationService->verify($value, $code)]);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
