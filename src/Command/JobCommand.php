<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::JOB_RUN,
    description: 'Execute the next pending job if exists. If not execute the oldest due scheduled job if exists.',
    hidden: false,
    aliases: ['ems:job:run']
)]
class JobCommand extends AbstractCommand
{
    private const string OPTION_DUMP = 'dump';
    private const string OPTION_TAG = 'tag';
    private const string USER_JOB_COMMAND = 'User-Job-Command';

    private bool $dump = false;
    private ?string $tag = null;

    public function __construct(
        private readonly JobService $jobService,
        private readonly ReleaseService $releaseService,
        private readonly string $dateFormat,
        private readonly string $cleanJobsTimeString,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this

            ->addOption(self::OPTION_DUMP, null, InputOption::VALUE_NONE, 'Shows the job\'s output at the end of the execution')
            ->addOption(self::OPTION_TAG, null, InputOption::VALUE_OPTIONAL, 'Will treat the next scheduled job flagged with the provided tag (do not execute pending jobs)')
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->dump = $this->getOptionBool(self::OPTION_DUMP);
        $this->tag = $this->getOptionStringNull(self::OPTION_TAG);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Job - Run');

        if ($this->processReleases() || $this->processNextJob() || $this->processNextScheduledJob()) {
            return self::EXECUTE_SUCCESS;
        }

        $this->io->comment('Nothing to run. Cleaning jobs.');
        $this->jobService->cleanJob(self::USER_JOB_COMMAND, $this->cleanJobsTimeString);

        return self::EXECUTE_SUCCESS;
    }

    private function processReleases(): bool
    {
        $releases = $this->releaseService->findReadyAndDue();
        if (0 === \count($releases)) {
            $this->io->comment('No releases scheduled to treat');

            return false;
        }

        foreach ($releases as $release) {
            $this->releaseService->executeRelease($release, true);
            $this->io->writeln(\sprintf('Release %s has been published', $release->getName()));
        }

        return true;
    }

    private function processNextJob(): bool
    {
        $nextJob = $this->jobService->nextJob($this->tag);

        if (null === $nextJob) {
            $this->io->comment('No jobs pending to treat');

            return false;
        }

        return $this->executeJob($nextJob);
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
        $this->io->listing([
            \sprintf('ID: %d', $job->getId()),
            \sprintf('Command: %s', $job->getCommand()),
            \sprintf('User: %s', $job->getUser()),
            \sprintf('Created: %s', $job->getCreated()->format($this->dateFormat)),
        ]);

        $start = new \DateTime();
        try {
            $this->jobService->run($job);
        } catch (\Throwable $e) {
            $this->jobService->finish($job->getId());
            throw $e;
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
            $this->io->section('Job\'s output:');
            $this->io->write($jobLog);
            $this->io->section('End of job\'s output');
        }
    }
}
