<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Repository\UploadedAssetRepository;

class UploadedFileService implements EntityServiceInterface
{
    private UploadedAssetRepository $uploadedAssetRepository;

    public function __construct(UploadedAssetRepository $uploadedAssetRepository)
    {
        $this->uploadedAssetRepository = $uploadedAssetRepository;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context && ($context['available'] ?? false)) {
            return $this->uploadedAssetRepository->getAvailable($from, $size, $orderField, $orderDirection, $searchValue);
        }

        return $this->uploadedAssetRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'UploadedAsset';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->uploadedAssetRepository->searchCount($searchValue);
    }

    /**
     * @param string[] $ids
     */
    public function toggleFileEntitiesVisibility(array $ids): void
    {
        foreach ($ids as $id) {
            $this->uploadedAssetRepository->toggleVisibility($id);
        }
    }
}
