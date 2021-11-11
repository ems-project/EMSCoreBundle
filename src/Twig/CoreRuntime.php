<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class CoreRuntime implements RuntimeExtensionInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function logNotice(string $notice): void
    {
        $this->logger->notice($notice);
    }

    public function logWarning(string $warning): void
    {
        $this->logger->warning($warning);
    }

    public function logError(string $error): void
    {
        $this->logger->error($error);
    }
}
