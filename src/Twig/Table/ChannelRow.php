<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Table;

use EMS\CoreBundle\Entity\Channel;

final class ChannelRow implements TableRowInterface
{
    /**
     * @var Channel
     */
    private $channel;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return Channel
     */
    public function getData(): object
    {
        return $this->channel;
    }
}
