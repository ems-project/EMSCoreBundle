<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;

final readonly class ChannelService implements EntityServiceInterface
{
    public function __construct(private ChannelRepository $channelRepository, private LoggerInterface $logger)
    {
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

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return Channel[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->channelRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'channel';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'channels',
            'Channel',
            'Channels',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->channelRepository->counter($searchValue);
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->channelRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        $schedule = Channel::fromJson($json, $entity);
        $this->update($schedule);

        return $schedule;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $channel = Channel::fromJson($json);
        if (null !== $name && $channel->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Filter name mismatched: %s vs %s', $channel->getName(), $name));
        }
        $this->update($channel);

        return $channel;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $channel = $this->channelRepository->getByName($name);
        if (null === $channel) {
            throw new \RuntimeException(\sprintf('Filter %s not found', $name));
        }
        $id = $channel->getId();
        $this->delete($channel);

        return $id;
    }
}
