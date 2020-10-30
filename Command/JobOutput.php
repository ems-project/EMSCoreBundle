<?php

namespace EMS\CoreBundle\Command;

use Symfony\Component\Console\Output\Output;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\Job;

class JobOutput extends Output
{
    /** @var Registry  */
    private $doctrine;

    /** @var Job  */
    private $job;

    public function __construct(Registry $doctrine, Job $job)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->job = $job;
    }

    public function doWrite($message, $newline): void
    {
        $this->job->setStatus($message);
        $this->job->setOutput($this->job->getOutput() . $this->getFormatter()->format($message) . ($newline ? PHP_EOL : ''));
        $manager = $this->doctrine->getManager();
        $manager->persist($this->job);
        $manager->flush();
    }
}
