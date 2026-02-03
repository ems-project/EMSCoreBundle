<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Service\JobService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

        if (null === $command) {
            throw new BadRequestHttpException('Command not found');
        }

        $job = $this->jobService->createCommand($user, $command);

        return new JsonResponse([
            'success' => true,
            'jobId' => (string) $job->getId(),
        ]);
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
