<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ManagedAlias;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class ManagedAliasManager implements EntityServiceInterface
{
    public function __construct(private readonly ManagedAliasRepository $repository, private readonly string $instanceId)
    {
    }

    public function delete(ManagedAlias $entity): void
    {
        $this->repository->delete($entity);
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->repository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'managed-alias';
    }

    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'managed-aliases',
            'manage-aliases',
            'manage-aliase',
            'managedaliase',
            'managedaliases',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->repository->counter($searchValue);
    }

    #[\Override]
    public function getByItemName(string $name): ?ManagedAlias
    {
        return $this->repository->findByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof ManagedAlias) {
            throw new \RuntimeException('Unexpected ManagedAlias object');
        }
        $i18n = ManagedAlias::fromJson($json, $entity);
        $this->repository->update($i18n);

        return $i18n;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $managedAlias = ManagedAlias::fromJson($json);
        if (null !== $name && $managedAlias->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Manage alias name mismatched: %s vs %s', $managedAlias->getName(), $name));
        }
        $this->update($managedAlias);

        return $managedAlias;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $managedAlias = $this->repository->findByName($name);
        if (null === $managedAlias) {
            throw new \RuntimeException(\sprintf('Manage alias %s not found', $name));
        }
        $id = $managedAlias->getId();
        $this->repository->delete($managedAlias);

        return (string) $id;
    }

    public function update(ManagedAlias $managedAlias): void
    {
        if (!$managedAlias->hasAlias()) {
            $managedAlias->setAlias($this->instanceId);
        }
        $managedAlias->setName(new Encoder()->slug($managedAlias->getName())->toString());
        $this->repository->update($managedAlias);
    }
}
