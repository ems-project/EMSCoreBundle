<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Handler;

use EMS\CommonBundle\Exception\RunnerNotFoundException;
use EMS\CommonBundle\Runner\RunnerManager;
use EMS\CoreBundle\Core\Messenger\Message\JobMessage;
use EMS\CoreBundle\Service\JobService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class JobHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private JobService $service,
        private RunnerManager $runnerManager,
    ) {
    }

    public function __invoke(JobMessage $message): void
    {
        $job = $this->service->getById($message->getContent());
        if (null === $job) {
            $this->logger->warning('job_handler.job_not_found', ['jobId' => $message->getContent()]);

            return;
        }
        $tag = $job->getTag() ?? '';
        if ('' === $tag) {
            $this->service->run($job);
        }

        try {
            $runnerId = $this->runnerManager->delegateJob($tag, (string) $job->getId(), $job->getCommand());
            $this->logger->info('job_handler.runner_started', ['jobId' => $runnerId]);
        } catch (RunnerNotFoundException) {
            $this->logger->info('job_handler.runner_not_found', ['tag' => $tag]);
        }
    }
}
