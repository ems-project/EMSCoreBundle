<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Job;

use EMS\CoreBundle\Repository\ScheduleRepository;
use Psr\Log\LoggerInterface;

class ScheduleManager
{
    private ScheduleRepository $scheduleRepository;
    private LoggerInterface $logger;

    public function __construct(ScheduleRepository $scheduleRepository, LoggerInterface $logger)
    {
        $this->scheduleRepository = $scheduleRepository;
        $this->logger = $logger;
    }
}
