<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components\Layout;

use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Service\JobService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class ControlSidebarComponent
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly Security $security,
    ) {
    }

    /**
     * @return Job[]
     */
    public function getJobs(): array
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $this->jobService->findByUser($user) : [];
    }
}
