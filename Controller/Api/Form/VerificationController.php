<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CoreBundle\Service\Form\Verification\CreateVerificationRequest;
use EMS\CoreBundle\Service\Form\Verification\FormVerificationException;
use EMS\CoreBundle\Service\Form\Verification\FormVerificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class VerificationController extends AbstractController
{
    /** @var FormVerificationService */
    private $formVerificationService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(FormVerificationService $formVerificationService, LoggerInterface $logger)
    {
        $this->formVerificationService = $formVerificationService;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/forms/verifications", defaults={"_format": "json"}, methods={"POST"})
     */
    public function createVerification(Request $request): Response
    {
        try {
            return new JsonResponse($this->formVerificationService->create(new CreateVerificationRequest($request)));
        } catch (FormVerificationException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getHttpCode());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @Route("/api/forms/verifications", defaults={"_format": "json"}, methods={"GET"})
     */
    public function getVerification(Request $request): Response
    {
        try {
            $value = $request->get('value', null);
            if (null === $value) {
                throw new FormVerificationException('value is required!');
            }

            return new JsonResponse($this->formVerificationService->get($value));
        } catch (FormVerificationException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getHttpCode());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
