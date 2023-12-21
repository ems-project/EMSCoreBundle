<?php

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

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->repository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'managed-alias';
    }

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

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->repository->counter($searchValue);
    }

    public function getByItemName(string $name): ?ManagedAlias
    {
        return $this->repository->findByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof ManagedAlias) {
            throw new \RuntimeException('Unexpected ManagedAlias object');
        }
        $i18n = ManagedAlias::fromJson($json, $entity);
        $this->repository->update($i18n);

        return $i18n;
    }

    public function createEntityFromJson(string $json, string $name = null): EntityInterface
    {
        $managedAlias = ManagedAlias::fromJson($json);
        if (null !== $name && $managedAlias->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Manage alias name mismatched: %s vs %s', $managedAlias->getName(), $name));
        }
        $this->update($managedAlias);

        return $managedAlias;
    }

    public function deleteByItemName(string $name): string
    {
        $managedAlias = $this->repository->findByName($name);
        if (null === $managedAlias) {
            throw new \RuntimeException(\sprintf('Manage alias %s not found', $name));
        }
        $id = $managedAlias->getId();
        $this->repository->delete($managedAlias);

        return \strval($id);
    }

    public function update(ManagedAlias $managedAlias): void
    {
        if (!$managedAlias->hasAlias()) {
            $managedAlias->setAlias($this->instanceId);
        }
        $encoder = new Encoder();
        $name = $managedAlias->getName();
        $webalized = $encoder->webalize($name);
        $managedAlias->setName($webalized);
        $this->repository->update($managedAlias);
    }
}
