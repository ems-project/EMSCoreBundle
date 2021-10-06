<?php

namespace EMS\CoreBundle\Core\Job;

use EMS\CoreBundle\Repository\JobRepository;
use Symfony\Component\Console\Output\Output;

class JobOutput extends Output
{
    private int $jobId;
    private JobRepository $jobRepository;

    public function __construct(JobRepository $jobRepository, int $jobId)
    {
        parent::__construct();
        $this->jobRepository = $jobRepository;
        $this->jobId = $jobId;
    }

    public function doWrite($message, $newline): void
    {
        $job = $this->jobRepository->findById($this->jobId);
        $job->setStatus($message);
        $job->setOutput($job->getOutput().$this->getFormatter()->format($message).($newline ? \PHP_EOL : ''));

        $this->jobRepository->save($job);
    }
}
