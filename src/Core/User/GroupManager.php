<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Group;
use EMS\CoreBundle\Repository\GroupRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class GroupManager implements EntityServiceInterface
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
    ) {
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->groupRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'group';
    }

    public function getAliasesName(): array
    {
        return [
            'groups',
            'Group',
            'Groups',
        ];
    }

    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->groupRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?Group
    {
        return $this->groupRepository->getByName($name);
    }

    public function getByItemId(string $id): ?Group
    {
        return $this->groupRepository->getById($id);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof Group) {
            throw new \RuntimeException('Unexpected group object');
        }
        $group = Group::fromJson($json, $entity);
        $this->groupRepository->save($group);

        return $group;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $group = Group::fromJson($json);
        $this->groupRepository->save($group);

        return $group;
    }

    public function deleteByItemName(string $name): string
    {
        $group = $this->groupRepository->getByName($name);
        if (null === $group) {
            throw new \RuntimeException(\sprintf('Form %s not found', $name));
        }
        $id = $group->getId();
        $this->groupRepository->deleteGroupByIds([$group->getId()]);

        return $id;
    }

    public function delete(Group $group): void
    {
        $this->groupRepository->deleteGroupByIds([$group->getId()]);
    }

    public function save(Group $group): void
    {
        if (!$group->isLabelDefined()) {
            $group->setLabel($group->getName());
        }

        $group->setName(new Encoder()->slug(text: $group->getName(), separator: '_')->toString());

        $this->groupRepository->save($group);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        $this->groupRepository->deleteGroupByIds($ids);
    }
}
