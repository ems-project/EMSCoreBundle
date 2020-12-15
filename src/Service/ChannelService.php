<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Helper\Text\Encoder;
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

    public function update(Channel $channel): void
    {
        if (0 === $channel->getOrderKey()) {
            $channel->setOrderKey($this->channelRepository->counter() + 1);
        }
        $encoder = new Encoder();
        $slug = $channel->getSlug();
        if (null === $slug) {
            throw new \RuntimeException('Unexpected null slug');
        }
        $webalized = $encoder->webalize($slug);
        if (null === $webalized) {
            throw new \RuntimeException('Unexpected null webalized slug');
        }
        $channel->setSlug($webalized);
        $this->channelRepository->create($channel);
    }

    public function delete(Channel $channel): void
    {
        $this->channelRepository->delete($channel);
    }
}
