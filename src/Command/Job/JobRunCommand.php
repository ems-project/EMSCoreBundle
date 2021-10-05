<?php

namespace EMS\CoreBundle\Command\Job;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class JobRunCommand extends Command
{
    /** @var JobService */
    private $jobService;
    /** @var SymfonyStyle */
    private $io;
    /**
     * @var string
     */
    private $dateFormat;

    protected static $defaultName = Commands::JOB_RUN;

    public function __construct(JobService $jobService, string $dateFormat)
    {
        parent::__construct();
        $this->jobService = $jobService;
        $this->dateFormat = $dateFormat;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Execute the next pending job if exist')
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
        $job = $this->jobService->findNext();
        if (null === $job) {
            $this->io->comment('No pending job to treat.');

            return 0;
        }

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
}
