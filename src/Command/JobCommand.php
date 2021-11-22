<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\Job\ScheduleManager;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Entity\Schedule;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class JobCommand extends AbstractCommand
{
    private const USER_JOB_COMMAND = 'User-Job-Command';
    private JobService $jobService;
    private string $dateFormat;
    private ScheduleManager $scheduleManager;
    private string $cleanJobsTimeString;

    public function __construct(JobService $jobService, ScheduleManager $scheduleManager, string $dateFormat, string $cleanJobsTimeString)
    {
        parent::__construct();
        $this->jobService = $jobService;
        $this->dateFormat = $dateFormat;
        $this->scheduleManager = $scheduleManager;
        $this->cleanJobsTimeString = $cleanJobsTimeString;
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:job:run')
            ->setDescription('Execute the next pending job if exist. If not execute the oldest due scheduled job if exist.')
            ->addOption(
                'dump',
                null,
                InputOption::VALUE_NONE,
                'Shows the job\'s output at the end of the execution'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Job');

        $job = $this->jobService->findNext();
        $schedule = null;
        if (null === $job) {
            $this->io->comment('No pending job to treat. Looking for due scheduled job.');
            $schedule = $this->scheduleManager->findNext();
            $job = $this->jobFomSchedule($schedule);
        }

        if (null === $job) {
            $this->io->comment('Nothing to run. Cleaning jobs.');
            $this->cleanJobs();

            return 0;
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

        if (true !== $input->getOption('dump')) {
            return 0;
        }

        $jobLog = $job->getOutput();
        if (null === $jobLog) {
            $this->io->write('Empty output');
        } else {
            $this->io->section('Job\'s output:');
            $output->write($jobLog);
            $this->io->section('End of job\'s output');
        }

        return 0;
    }

    private function jobFomSchedule(?Schedule $schedule): ?Job
    {
        if (null === $schedule) {
            return null;
        }
        $startDate = $schedule->getPreviousRun();
        if (null === $startDate) {
            throw new \RuntimeException('Unexpected null start date');
        }

        return $this->jobService->initJob(self::USER_JOB_COMMAND, $schedule->getCommand(), $startDate);
    }

    private function cleanJobs(): void
    {
        $this->jobService->cleanJob(self::USER_JOB_COMMAND, $this->cleanJobsTimeString);
    }
}
