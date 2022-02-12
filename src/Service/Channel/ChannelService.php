<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

final class ChannelService implements EntityServiceInterface
{
    private ChannelRepository $channelRepository;
    private LoggerInterface $logger;

    public function __construct(ChannelRepository $channelRepository, LoggerInterface $logger)
    {
        $this->channelRepository = $channelRepository;
        $this->logger = $logger;
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
        $webalized = $encoder->webalize($channel->getName());
        if (null === $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $channel->setName($webalized);
        $this->channelRepository->create($channel);
    }

    public function delete(Channel $channel): void
    {
        $name = $channel->getName();
        $this->channelRepository->delete($channel);
        $this->logger->warning('log.service.channel.delete', [
            'name' => $name,
        ]);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->channelRepository->getByIds($ids) as $channel) {
            $this->delete($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->channelRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->channelRepository->create($channel);
        }
    }

    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @param mixed $context
     *
     * @return Channel[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->channelRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'channel';
    }

    /**
     * @param mixed $context
     */
    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->channelRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->channelRepository->findRegistered($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}
