<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Event\DispatchToWebhookEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class CoreRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private EventDispatcherInterface $dispatcher,
    ) {
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

    /**
     * @param mixed[] $data
     */
    public function dispatchWebhook(string $eventName, array $data = []): void
    {
        $this->dispatcher->dispatch(new DispatchToWebhookEvent($eventName, $data));
    }
}
