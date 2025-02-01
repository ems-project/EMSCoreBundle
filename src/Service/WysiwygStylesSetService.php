<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Repository\WysiwygStylesSetRepository;
use Psr\Log\LoggerInterface;

class WysiwygStylesSetService implements EntityServiceInterface
{
    public function __construct(
        private readonly WysiwygStylesSetRepository $wysiwygStylesSetRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->wysiwygStylesSetRepository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $styleSet = WysiwygStylesSet::fromJson($json);
        if (null !== $name && $styleSet->getName() !== $name) {
            throw new \RuntimeException(\sprintf('WYSIWYG StylesSet name mismatched: %s vs %s', $styleSet->getName(), $name));
        }
        $this->wysiwygStylesSetRepository->update($styleSet);

        return $styleSet;
    }

    public function delete(WysiwygStylesSet $wysiwygStylesSet): void
    {
        $this->wysiwygStylesSetRepository->delete($wysiwygStylesSet);
        $this->logger->notice('service.wysiwyg_styles_set.deleted', [
            'wysiwyg_styles_set_name' => $wysiwygStylesSet->getName(),
        ]);
    }

    public function deleteByIds(string ...$ids): void
    {
        $styleSets = $this->wysiwygStylesSetRepository->getByIds(...$ids);
        foreach ($styleSets as $styleSet) {
            $this->delete($styleSet);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $styleSet = $this->wysiwygStylesSetRepository->getByName($name);
        if (null === $styleSet) {
            throw new \RuntimeException(\sprintf('WWYSIWYG StylesSet %s not found', $name));
        }
        $id = $styleSet->getId();
        $this->wysiwygStylesSetRepository->delete($styleSet);

        return (string) $id;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->wysiwygStylesSetRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('s.%s', $orderField), $orderDirection);
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
            'wysiwyg-style-sets',
            'WysiwygStyleSet',
            'WysiwygStyleSets',
        ];
    }

    public function getById(int $id): ?WysiwygStylesSet
    {
        return $this->wysiwygStylesSetRepository->findById($id);
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->wysiwygStylesSetRepository->getByName($name);
    }

    public function getByName(?string $name): ?WysiwygStylesSet
    {
        if (null === $name) {
            return $this->getStylesSets()[0] ?? null;
        }

        return $this->wysiwygStylesSetRepository->getByName($name);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'wysiwyg-style-set';
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function getStylesSets(): array
    {
        static $stylesSets = null;

        return $stylesSets ?? $this->wysiwygStylesSetRepository->findAll();
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
            $wysiwygStylesSet = $this->wysiwygStylesSetRepository->getById($id);
            $wysiwygStylesSet->setOrderKey($counter++);
            $this->wysiwygStylesSetRepository->update($wysiwygStylesSet);
        }
    }

    public function update(WysiwygStylesSet $wysiwygStylesSet): void
    {
        $this->wysiwygStylesSetRepository->update($wysiwygStylesSet);
        $this->logger->notice('service.wysiwyg_styles_set.updated', [
            'wysiwyg_styles_set_name' => $wysiwygStylesSet->getName(),
        ]);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof WysiwygStylesSet) {
            throw new \RuntimeException('Unexpected object class');
        }
        $stylesSet = WysiwygStylesSet::fromJson($json, $entity);
        $this->wysiwygStylesSetRepository->update($stylesSet);

        return $stylesSet;
    }
}
