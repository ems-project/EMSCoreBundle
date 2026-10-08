<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Helper\EmsCoreResponse;
use EMS\CoreBundle\Service\JobService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

class JobApiController
{
    public function __construct(
        private readonly JobService $jobService
    ) {
    }

    public function create(Request $request, UserInterface $user): JsonResponse
    {
        $content = Json::decode($request->getContent());
        $command = $content['command'] ?? null;
        $tag = $content['tag'] ?? null;

        if (null === $command) {
            throw new BadRequestHttpException('Command not found');
        }

        $job = $this->jobService->createCommand($user, $command, $tag);

        return new JsonResponse([
            'success' => true,
            'jobId' => (string) $job->getId(),
        ]);
    }

    public function write(Request $request, int $job): Response
    {
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $data = Json::decode($content);
        $message = (string) ($data['message'] ?? '');
        $newLine = (bool) ($data['new-line'] ?? false);
        $this->jobService->write($job, $message, $newLine);

        return EmsCoreResponse::createJsonResponse($request, true);
    }

    public function startNextJob(Request $request, UserInterface $user, string $tag): Response
    {
        $jobId = $request->query->get('job_id');
        if (null !== $jobId) {
            $job = $this->jobService->getById((int) $jobId);
            if (null === $job) {
                throw new NotFoundHttpException(\sprintf('job with id %s not found', $jobId));
            }
            if ($job->getTag() !== $tag) {
                throw new \RuntimeException(\sprintf('job tag mismatched %s', $job->getTag()));
            }
            if ($job->getStarted()) {
                throw new \RuntimeException('job already started');
            }
        } else {
            $job = $this->jobService->nextJob($tag);
        }
        if (null === $job) {
            $job = $this->jobService->nextJobScheduled($user->getUserIdentifier(), $tag);
        }

        if (null === $job) {
            return EmsCoreResponse::createJsonResponse($request, true, ['message' => 'no next job']);
        }

        $this->jobService->start($job);

        return EmsCoreResponse::createJsonResponse($request, true, [
            'message' => \sprintf('job %d flagged has started', $job->getId()),
            'job_id' => (string) $job->getId(),
            'command' => $job->getCommand(),
            'output' => $job->getOutput(),
        ]);
    }

    public function jobFailed(Request $request, int $job): Response
    {
        $content = $request->getContent();
        if (!\is_string($content)) {
            throw new \RuntimeException('Unexpected non string content');
        }
        $data = Json::decode($content);
        $this->jobService->finish($job, $data['message'] ?? 'job failed');

        return EmsCoreResponse::createJsonResponse($request, true);
    }

    public function jobCompleted(Request $request, int $job): Response
    {
        $this->jobService->finish($job);

        return EmsCoreResponse::createJsonResponse($request, true);
    }

    public function status(Job $job): Response
    {
        return new JsonResponse([
            'id' => (string) $job->getId(),
            'created' => $job->getCreated()->format('c'),
            'modified' => $job->getModified()->format('c'),
            'command' => $job->getCommand(),
            'user' => $job->getUser(),
            'done' => $job->getDone(),
            'output' => $job->getOutput(),
            'started' => $job->getStarted(),
            'status' => $job->getStatus(),
        ]);
    }
}
