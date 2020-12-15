<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Repository\ChannelRepository;

final class ChannelService
{
    /**
     * @var ChannelRepository
     */
    private $channelRepository;

    public function __construct(ChannelRepository $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    /**
     * @return Channel[]
     */
    public function getAll(): array
    {
        return $this->channelRepository->getAll();
    }

    public function create(Channel $channel): void
    {
        $channel->setOrderKey($this->channelRepository->counter() + 1);
        $this->channelRepository->create($channel);
    }
}
