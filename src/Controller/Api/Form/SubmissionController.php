<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CoreBundle\Service\Form\Submission\FormSubmissionException;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\Helpers\File\File;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use EMS\SubmissionBundle\Request\DatabaseRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly FormSubmissionService $formSubmissionService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function submit(Request $request): JsonResponse
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

    public function submission(Request $request, string $submissionId): JsonResponse
    {
        if (null === $submission = $this->formSubmissionService->findById($submissionId)) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        if ($request->query->has('property')) {
            $property = Type::string($request->query->get('property'));

            return new JsonResponse([$property => $this->formSubmissionService->getProperty($submission, $property)]);
        }

        return new JsonResponse($submission);
    }

    public function submissionFile(string $submissionId, string $submissionFileId): JsonResponse|StreamedResponse
    {
        if (null === $submissionFile = $this->formSubmissionService->findFile($submissionId, $submissionFileId)) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        $response = new StreamedResponse(function () use ($submissionFile) {
            if (null === $fileStream = $submissionFile->getFile()) {
                return;
            }

            while (!\feof($fileStream)) {
                echo \fread($fileStream, File::DEFAULT_CHUNK_SIZE);
                \flush();
            }
            \fclose($fileStream);
        });

        $response->headers->set('Content-Type', $submissionFile->getMimeType());
        $response->headers->set('Content-Length', $submissionFile->getSize());
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $submissionFile->getFilename()
        ));

        return $response;
    }
}
