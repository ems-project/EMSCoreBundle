<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

use EMS\CoreBundle\Entity\Channel;

final class ChannelTable implements TableInterface
{
    /**
     * @var Channel[]
     */
    private $channels;

    /**
     * @param Channel[] $channels
     */
    public function __construct(array $channels)
    {
        $this->channels = $channels;
    }

    public function getTitleTransKey(): string
    {
        return 'channel.table.title';
    }

    public function getTotal(): int
    {
        return \count($this->channels);
    }

    public function isSortable(): bool
    {
        return true;
    }
}
