<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Psr\Log\LoggerInterface;

class WysiwygProfileService implements EntityServiceInterface
{
    public function __construct(
        private readonly WysiwygProfileRepository $wysiwygProfileRepository,
        private readonly LoggerInterface $logger)
    {
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->wysiwygProfileRepository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $profile = WysiwygProfile::fromJson($json);
        if (null !== $name && $profile->getName() !== $name) {
            throw new \RuntimeException(\sprintf('WYSIWYG Profile name mismatched: %s vs %s', $profile->getName(), $name));
        }
        $this->wysiwygProfileRepository->update($profile);

        return $profile;
    }

    public function delete(WysiwygProfile $wysiwygProfile): void
    {
        $this->wysiwygProfileRepository->delete($wysiwygProfile);
        $this->logger->notice('service.wysiwyg_profile.deleted', [
            'profile_name' => $wysiwygProfile->getName(),
        ]);
    }

    public function deleteByIds(string ...$ids): void
    {
        $profiles = $this->wysiwygProfileRepository->getByIds(...$ids);
        foreach ($profiles as $profile) {
            $this->delete($profile);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $profile = $this->wysiwygProfileRepository->getByName($name);
        if (null === $profile) {
            throw new \RuntimeException(\sprintf('WYSIWYG Profile %s not found', $name));
        }
        $id = $profile->getId();
        $this->wysiwygProfileRepository->delete($profile);

        return \strval($id);
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->wysiwygProfileRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('p.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'wysiwyg-profiles',
            'WysiwygProfile',
            'WysiwygProfiles',
        ];
    }

    public function getById(int $id): ?WysiwygProfile
    {
        return $this->wysiwygProfileRepository->findById($id);
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->wysiwygProfileRepository->getByName($name);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'wysiwyg-profile';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $wysiwygProfile = $this->wysiwygProfileRepository->getById($id);
            $wysiwygProfile->setOrderKey($counter++);
            $this->wysiwygProfileRepository->update($wysiwygProfile);
        }
    }

    public function update(WysiwygProfile $wysiwygProfile): void
    {
        $this->wysiwygProfileRepository->update($wysiwygProfile);
        $this->logger->notice('service.wysiwyg_profile.updated', [
            'profile_name' => $wysiwygProfile->getName(),
        ]);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected object class');
        }
        $profile = WysiwygProfile::fromJson($json, $entity);
        $this->wysiwygProfileRepository->update($profile);

        return $profile;
    }
}
