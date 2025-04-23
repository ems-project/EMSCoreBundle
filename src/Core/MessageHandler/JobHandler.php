<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\MessageHandler;

use EMS\CoreBundle\Core\Message\Job;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class JobHandler
{
    public function __construct(
        private JobService $service,
    ) {
    }

    public function __invoke(Job $message): void
    {
        if ($job = $this->service->getById($message->getContent())) {
            $this->service->run($job);
        }
    }
}
