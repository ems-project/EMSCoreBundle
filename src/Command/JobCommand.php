<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class JobCommand extends AbstractCommand
{
    private const OPTION_DUMP = 'dump';
    private const OPTION_TAG = 'tag';
    protected static $defaultName = 'ems:job:run';
    private const USER_JOB_COMMAND = 'User-Job-Command';
    private bool $dump = false;
    private ?string $tag = null;

    public function __construct(
        private readonly JobService $jobService,
        private readonly string $dateFormat,
        private readonly string $cleanJobsTimeString
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Execute the next pending job if exists. If not execute the oldest due scheduled job if exists.')
            ->addOption(
                self::OPTION_DUMP,
                null,
                InputOption::VALUE_NONE,
                'Shows the job\'s output at the end of the execution'
            )
            ->addOption(
                self::OPTION_TAG,
                null,
                InputOption::VALUE_OPTIONAL,
                'Will treat the next scheduled job flagged with the provided tag (don\'t execute pending jobs)'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->dump = $this->getOptionBool(self::OPTION_DUMP);
        $this->tag = $this->getOptionStringNull(self::OPTION_TAG);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Job');

        $job = $this->jobService->nextJob($this->tag);

        if (null === $job) {
            $this->io->comment('No pending job to treat. Looking for due scheduled job.');
            $job = $this->jobService->nextJobScheduled(self::USER_JOB_COMMAND, $this->tag);
        }

        if (null === $job) {
            $this->io->comment('Nothing to run. Cleaning jobs.');
            $this->cleanJobs();

            return self::EXECUTE_SUCCESS;
        }

        return $this->runJob($job, $input, $output);
    }

    /**
     * @throws \Throwable
     */
    protected function runJob(Job $job, InputInterface $input, OutputInterface $output): int
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

        $this->io->success(\sprintf('Job completed with the return status "%s" in %s', $job->getStatus(), $interval->format('%a days, %h hours, %i minutes and %s seconds')));

        if (!$this->dump) {
            return parent::EXECUTE_SUCCESS;
        }

        $jobLog = $job->getOutput();
        if (null === $jobLog) {
            $this->io->write('Empty output');
        } else {
            $this->io->section('Job\'s output:');
            $output->write($jobLog);
            $this->io->section('End of job\'s output');
        }

        return parent::EXECUTE_SUCCESS;
    }

    private function cleanJobs(): void
    {
        $this->jobService->cleanJob(self::USER_JOB_COMMAND, $this->cleanJobsTimeString);
    }
}
