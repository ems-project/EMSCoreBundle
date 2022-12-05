<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionException;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\SubmissionBundle\Request\DatabaseRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SubmissionController extends AbstractController
{
    public function __construct(private readonly FormSubmissionService $formSubmissionService, private readonly LoggerInterface $logger)
    {
    }

    public function submit(Request $request): Response
    {
        try {
            $json = Json::decode(\strval($request->getContent()));

            return new JsonResponse($this->formSubmissionService->submit(new DatabaseRequest($json)));
        } catch (FormSubmissionException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return new JsonResponse([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
