<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Runner\RunnerManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::JOB_RUN,
    description: 'Execute the next pending job if exists. If not execute the oldest due scheduled job if exists.',
    aliases: ['ems:job:run'],
    hidden: false
)]
class JobCommand extends AbstractCoreCommand
{
    private const string ARGUMENT_JOB_ID = 'job-id';
    private const string OPTION_DUMP = 'dump';
    private const string OPTION_TAG = 'tag';
    private const string USER_JOB_COMMAND = 'User-Job-Command';

    private bool $dump = false;
    private ?string $tag = null;
    private ?string $jobId = null;

    public function __construct(
        private readonly JobService $jobService,
        private readonly ReleaseService $releaseService,
        private readonly RunnerManager $runnerManager,
        private readonly string $dateFormat,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_JOB_ID, InputArgument::OPTIONAL, 'Job ID to execute')
            ->addOption(self::OPTION_DUMP, null, InputOption::VALUE_NONE, "Shows the job's output at the end of the execution")
            ->addOption(self::OPTION_TAG, null, InputOption::VALUE_OPTIONAL, 'Will treat the next scheduled job flagged with the provided tag (do not execute pending jobs)')
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->jobId = $this->getArgumentStringNull(self::ARGUMENT_JOB_ID);
        $this->dump = $this->getOptionBool(self::OPTION_DUMP);
        $this->tag = $this->getOptionStringNull(self::OPTION_TAG);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Job - Run');

        if ($this->processReleases() || $this->processNextJob() || $this->processNextScheduledJob() || $this->processNextScheduledRunner()) {
            return self::EXECUTE_SUCCESS;
        }

        $this->io->comment('Nothing to run. Cleaning jobs.');
        $this->jobService->clean(
            skipFailed: true,
            includeJobTime: true
        );

        return self::EXECUTE_SUCCESS;
    }

    private function processReleases(): bool
    {
        $releases = $this->releaseService->findReadyAndDue();
        if ([] === $releases) {
            $this->io->comment('No releases scheduled to treat');

            return false;
        }

        foreach ($releases as $release) {
            $this->releaseService->executeRelease($release, self::USER_JOB_COMMAND);
            $this->io->writeln(\sprintf('Release %s has been published', $release->getName()));
        }

        return true;
    }

    private function processNextJob(): bool
    {
        if (null !== $this->jobId) {
            $nextJob = $this->jobService->getById((int) $this->jobId);
            if (null === $nextJob) {
                throw new \RuntimeException(\sprintf('Job %d not found', $this->jobId));
            }
            if (null !== $this->tag && $this->tag !== $nextJob->getTag()) {
                throw new \RuntimeException(\sprintf('job tag mismatched %s', $nextJob->getTag()));
            }
            if ($nextJob->getStarted()) {
                throw new \RuntimeException('job already started');
            }
        } else {
            $nextJob = $this->jobService->nextJob($this->tag);
        }

        if (null !== $nextJob) {
            return $this->executeJob($nextJob);
        }

        if (null !== $this->tag) {
            $this->io->comment(\sprintf('No jobs pending to treat for tag %s', $this->tag));

            return false;
        }
        foreach ($this->runnerManager->getTags() as $tag) {
            $nextJob = $this->jobService->nextJob($tag);
            if (null === $nextJob) {
                continue;
            }
            $runnerId = $this->runnerManager->delegateJob($tag, (string) $nextJob->getId(), $nextJob->getCommand());
            $this->io->title(\sprintf('Runner with ID: %d has been initialized', $runnerId));
            $this->getListing($nextJob);

            return true;
        }

        $this->io->comment('No jobs pending to treat');

        return false;
    }

    private function processNextScheduledJob(): bool
    {
        $nextScheduledJob = $this->jobService->nextJobScheduled(self::USER_JOB_COMMAND, $this->tag);
        if (null === $nextScheduledJob) {
            $this->io->comment('No jobs scheduled to treat');

            return false;
        }

        return $this->executeJob($nextScheduledJob);
    }

    private function executeJob(Job $job): bool
    {
        $this->io->title('Preparing the job');
        $this->getListing($job);

        $start = new \DateTime();
        try {
            $this->jobService->run($job);
        } catch (\Throwable $throwable) {
            $this->jobService->finish($job->getId());
            throw $throwable;
        }

        $interval = \date_diff($start, new \DateTime());

        $this->io->success(\sprintf(
            'Job completed with the return status "%s" in %s',
            $job->getStatus(),
            $interval->format('%a days, %h hours, %i minutes and %s seconds')
        ));

        if ($this->dump) {
            $this->outputJobLog($job);
        }

        return true;
    }

    private function outputJobLog(Job $job): void
    {
        $jobLog = $job->getOutput();
        if (null === $jobLog) {
            $this->io->write('Empty output');
        } else {
            $this->io->section("Job's output:");
            $this->io->write($jobLog);
            $this->io->section("End of job's output");
        }
    }

    private function processNextScheduledRunner(): bool
    {
        foreach ($this->runnerManager->getTags() as $tag) {
            $job = $this->jobService->nextJobScheduled(self::USER_JOB_COMMAND, $tag, false);
            if (null === $job) {
                continue;
            }

            $runnerId = $this->runnerManager->delegateJob($tag, (string) $job->getId(), $job->getCommand());
            $this->io->title(\sprintf('Runner with ID: %d has been initialized', $runnerId));
            $this->getListing($job);

            return true;
        }
        $this->io->comment('No runner scheduled to start');

        return false;
    }

    private function getListing(Job $job): void
    {
        $this->io->listing([
            \sprintf('ID: %d', $job->getId()),
            \sprintf('Command: %s', $job->getCommand()),
            \sprintf('User: %s', $job->getUser()),
            \sprintf('Created: %s', $job->getCreated()->format($this->dateFormat)),
        ]);
    }
}
