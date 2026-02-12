<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

readonly class JobExtension
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
    #[AsTwigFunction(name: 'emsco_job')]
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
                'emsco_job_status',
                ['job' => $job->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }
}
