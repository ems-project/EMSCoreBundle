<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Environment;

use EMS\CoreBundle\Entity\Revision;
use Psr\Log\LogLevel;

class EnvironmentPublisher
{
    private Revision $revision;

    /** @var array<int, array{'level': string, 'revision': string, 'message': string}> */
    private array $messages;

    /**
     * @param array<int, array{'level': string, 'revision': string, 'message': string}> $messages
     */
    public function __construct(Revision $revision, array $messages)
    {
        $this->revision = $revision;
        $this->messages = $messages;
    }

    /**
     * @return array<int, array{'level': string, 'revision': string, 'message': string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function blockPublication(): bool
    {
        $importantMessages = \array_filter($this->messages, fn (array $message) => LogLevel::NOTICE !== $message['level']);

        return \count($importantMessages) > 0;
    }

    public function addNotice(string $message): void
    {
        $this->addMessage(LogLevel::NOTICE, $message);
    }

    public function addWarning(string $message): void
    {
        $this->addMessage(LogLevel::WARNING, $message);
    }

    public function addError(string $message): void
    {
        $this->addMessage(LogLevel::ERROR, $message);
    }

    private function addMessage(string $level, string $message): void
    {
        $this->messages[] = [
            'level' => $level,
            'revision' => (string) $this->revision,
            'message' => $message,
        ];
    }
}
