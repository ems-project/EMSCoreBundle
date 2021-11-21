<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Job;

use EMS\CoreBundle\Core\Job\ScheduleManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ScheduleController extends AbstractController
{
    private LoggerInterface $logger;
    private ScheduleManager $scheduleManager;

    public function __construct(LoggerInterface $logger, ScheduleManager $scheduleManager)
    {
        $this->logger = $logger;
        $this->scheduleManager = $scheduleManager;
    }
}
