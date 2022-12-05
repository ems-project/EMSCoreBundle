<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Repository\JobRepository;
use Symfony\Component\Console\Output\Output;

class JobOutput extends Output
{
    private const JOB_VERBOSITY = self::VERBOSITY_NORMAL;

    public function __construct(private readonly JobRepository $jobRepository, private readonly int $jobId)
    {
        parent::__construct(self::JOB_VERBOSITY);
    }

    /**
     * Do not allow symfony to overwrite the verbosity level.
     */
    public function setVerbosity(int $level): void
    {
        parent::setVerbosity(self::JOB_VERBOSITY);
    }

    public function doWrite(string $message, bool $newline): void
    {
        $job = $this->jobRepository->findById($this->jobId);
        $job->setStatus($message);
        $job->setOutput($job->getOutput().$this->getFormatter()->format($message).($newline ? PHP_EOL : ''));

        $this->jobRepository->save($job);
    }
}
