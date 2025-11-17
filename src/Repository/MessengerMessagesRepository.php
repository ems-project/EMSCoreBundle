<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Repository;

use Doctrine\DBAL\Connection;

readonly class MessengerMessagesRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function waitingCount(): int
    {
        $waiting = $this->connection->fetchOne('
            SELECT COUNT(*) 
            FROM messenger_messages 
            WHERE delivered_at IS NULL
        ');

        return (int) $waiting;
    }

    public function errorCount(): int
    {
        $error = $this->connection->fetchOne("
            SELECT COUNT(*) 
            FROM messenger_messages 
            WHERE queue_name = 'failed'
        ");

        return (int) $error;
    }
}
