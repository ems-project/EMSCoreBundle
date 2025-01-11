<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use Psr\Log\LoggerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CoreRuntime implements RuntimeExtensionInterface
{
    public function __construct(private LoggerInterface $logger)
    {
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
