<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Form;

use EMS\CoreBundle\Service\Form\Submission\FormSubmissionContext;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionException;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\Helpers\File\File;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use EMS\SubmissionBundle\Entity\FormSubmissionFile;
use EMS\SubmissionBundle\Request\DatabaseRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function Symfony\Component\String\u;

final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly FormSubmissionService $formSubmissionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getSubmissions(Request $request): JsonResponse
    {
        $fromDateTime = Type::nullableString($request->query->all()['from-datetime'] ?? null);
        $fromDateTime = $fromDateTime ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $fromDateTime) : null;
        if (false === $fromDateTime) {
            throw new BadRequestHttpException('The format of the "from" query parameter is incorrect.');
        }
        $toDateTime = Type::nullableString($request->query->all()['to-datetime'] ?? null);
        $toDateTime = $toDateTime ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $toDateTime) : null;
        if (false === $toDateTime) {
            throw new BadRequestHttpException('The format of the "to" query parameter is incorrect.');
        }
        $context = new FormSubmissionContext(
            $fromDateTime,
            $toDateTime,
            Type::nullableArray($request->query->all()['names'] ?? null),
            Type::nullableArray($request->query->all()['instances'] ?? null),
            Type::nullableArray($request->query->all()['locales'] ?? null),
        );
        $size = (int) ($request->query->all()['size'] ?? 50);
        $from = (int) ($request->query->all()['from'] ?? 0);
        $total = $this->formSubmissionService->count('', $context);
        $formSubmissions = $this->formSubmissionService->get($from, $size, null, 'asc', '', $context);

        return new JsonResponse([
            'total' => $total,
            'submissions' => $formSubmissions,
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        try {
            $json = Json::decode((string) $request->getContent());

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

        $data = $submission->toArray();
        $fileUrl = $request->query->get('fileUrl');

        if ($fileUrl && $submission->getFiles()->count() > 0) {
            $data['file_urls'] = $submission->getFiles()->map(
                fn (FormSubmissionFile $f) => u($fileUrl)
                ->replace('{SUBMISSION_ID}', $submission->getId())
                ->replace('{FILE_ID}', $f->getId())
                ->toString()
            )->toArray();
        }

        if ($request->query->has('property')) {
            $property = Type::string($request->query->get('property'));

            return new JsonResponse([$property => $this->formSubmissionService->getProperty($data, $property)]);
        }

        return new JsonResponse($data);
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
