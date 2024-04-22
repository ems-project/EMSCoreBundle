<?php

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Psr\Log\LoggerInterface;

class WysiwygProfileService implements EntityServiceInterface
{
    public function __construct(private readonly WysiwygProfileRepository $wysiwygProfileRepository, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return WysiwygProfile[]
     */
    public function getProfiles(): array
    {
        $profiles = $this->wysiwygProfileRepository->findAll();

        return $profiles;
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $wysiwyg_profile = $this->wysiwygProfileRepository->getById($id);
            $wysiwyg_profile->setOrderKey($counter++);
            $this->wysiwygProfileRepository->create($wysiwyg_profile);
        }
    }

    public function getById(int $id): ?WysiwygProfile
    {
        return $this->wysiwygProfileRepository->findById($id);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->wysiwygProfileRepository->getByIds($ids) as $wysiwygProfile) {
            $this->delete($wysiwygProfile);
        }
    }

    public function delete(WysiwygProfile $wysiwygProfile): void
    {
        $name = $wysiwygProfile->getName();
        $this->wysiwygProfileRepository->delete($wysiwygProfile);
        $this->logger->warning('log.service.wysiwyg_profile.delete', [
            'name' => $name,
        ]);
    }

    public function saveProfile(WysiwygProfile $profile): void
    {
        $this->wysiwygProfileRepository->update($profile);
        $this->logger->notice('service.wysiwyg_profile.updated', [
            'profile_name' => $profile->getName(),
        ]);
    }

    public function remove(WysiwygProfile $profile): void
    {
        $this->wysiwygProfileRepository->delete($profile);
        $this->logger->notice('service.wysiwyg_profile.deleted', [
            'profile_name' => $profile->getName(),
        ]);
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->wysiwygProfileRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'wysiwyg-profile';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [
            'wysiwyg-profiles',
            'WysiwygProfile',
            'WysiwygProfiles',
        ];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->wysiwygProfileRepository->counter($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->wysiwygProfileRepository->getByName($name);
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected object class');
        }
        $profile = WysiwygProfile::fromJson($json, $entity);
        $this->wysiwygProfileRepository->update($profile);

        return $profile;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $profile = WysiwygProfile::fromJson($json);
        if (null !== $name && $profile->getName() !== $name) {
            throw new \RuntimeException(\sprintf('WYSIWYG Profile name mismatched: %s vs %s', $profile->getName(), $name));
        }
        $this->wysiwygProfileRepository->update($profile);

        return $profile;
    }

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
}
