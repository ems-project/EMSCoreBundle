<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class JobRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private JobService $jobService,
        private UserManager $userManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{ 'id': int, 'started': bool, 'done': bool, 'created': string, 'statusUrl': string }
     */
    public function create(string $command, string $tag): array
    {
        $user = $this->userManager->getAuthenticatedUser();
        $job = $this->jobService->createCommand($user, $command, $tag);

        return [
            'id' => $job->getId(),
            'started' => $job->getStarted(),
            'done' => $job->getDone(),
            'created' => $job->getCreated()->format(\DateTimeInterface::ATOM),
            'statusUrl' => $this->urlGenerator->generate(
                'job.status',
                ['job' => $job->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }
}
