<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand as CommonAbstractCommand;
use Psr\Log\LoggerInterface;

abstract class AbstractCommand extends CommonAbstractCommand
{
    protected LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
