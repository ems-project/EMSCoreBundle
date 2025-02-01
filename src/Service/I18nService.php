<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Repository\I18nRepository;

class I18nService implements EntityServiceInterface
{
    public function __construct(private readonly I18nRepository $repository)
    {
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->repository->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $i18n = I18n::fromJson($json);
        if (null !== $name && $i18n->getIdentifier() !== $name) {
            throw new \RuntimeException(\sprintf('I18n name mismatched: %s vs %s', $i18n->getIdentifier(), $name));
        }
        $this->repository->update($i18n);

        return $i18n;
    }

    public function delete(I18n $i18n): void
    {
        $this->repository->delete($i18n);
    }

    public function deleteByIds(string ...$ids): void
    {
        $filters = $this->repository->getByIds(...$ids);
        foreach ($filters as $filter) {
            $this->delete($filter);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $i18n = $this->repository->findByIdentifier($name);
        if (null === $i18n) {
            throw new \RuntimeException(\sprintf('I18n %s not found', $name));
        }
        $id = $i18n->getId();
        $this->repository->delete($i18n);

        return (string) $id;
    }

    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->repository->makeQueryBuilder(searchValue: $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('i.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'internationalization',
            'internationalizations',
            'Internationalization',
            'Internationalizations',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getAsList(string $name): array
    {
        $i18n = $this->repository->findByIdentifier($name);
        if (null === $i18n) {
            return [];
        }
        $choice = [];
        foreach ($i18n->getContent() as $item) {
            $choice[$item['locale']] = $item['text'];
        }

        return $choice;
    }

    #[\Override]
    public function getByItemName(string $name): ?I18n
    {
        return $this->repository->findByIdentifier($name);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'i18n';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    public function update(I18n $i18n): void
    {
        $this->repository->update($i18n);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof I18n) {
            throw new \RuntimeException('Unexpected I18n object');
        }
        $i18n = I18n::fromJson($json, $entity);
        $this->repository->update($i18n);

        return $i18n;
    }
}
