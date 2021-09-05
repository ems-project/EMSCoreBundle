<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use Psr\Log\LoggerInterface;

class XliffService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
