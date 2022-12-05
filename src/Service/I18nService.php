<?php

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Repository\I18nRepository;

class I18nService implements EntityServiceInterface
{
    public function __construct(private readonly I18nRepository $repository)
    {
    }

    /**
     * @param array<string>|null $filters
     */
    public function counter(array $filters = null): int
    {
        $identifier = null;

        if (null != $filters && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }

        return $this->repository->countWithFilter($identifier);
    }

    public function delete(I18n $i18n): void
    {
        $this->repository->delete($i18n);
    }

    /**
     * @param array<string>|null $filters
     *
     * @return iterable|I18n[]
     */
    public function findAll(int $from, int $limit, array $filters = null): iterable
    {
        $identifier = null;

        if (null != $filters && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }

        return $this->repository->findByWithFilter($limit, $from, $identifier);
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
        return 'i18n';
    }

    public function getAliasesName(): array
    {
        return [
            'internationalization',
            'internationalizations',
            'Internationalization',
            'Internationalizations',
        ];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->repository->counter($searchValue);
    }

    public function getByItemName(string $name): ?I18n
    {
        return $this->repository->findByIdentifier($name);
    }

    /**
     * @return array<string, string>
     */
    public function getAsChoiceList(string $name): array
    {
        return \array_flip($this->getAsList($name));
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

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof I18n) {
            throw new \RuntimeException('Unexpected I18n object');
        }
        $i18n = I18n::fromJson($json, $entity);
        $this->repository->update($i18n);

        return $i18n;
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $i18n = I18n::fromJson($json);
        if (null !== $name && $i18n->getIdentifier() !== $name) {
            throw new \RuntimeException(\sprintf('I18n name mismatched: %s vs %s', $i18n->getIdentifier(), $name));
        }
        $this->repository->update($i18n);

        return $i18n;
    }

    public function deleteByItemName(string $name): string
    {
        $i18n = $this->repository->findByIdentifier($name);
        if (null === $i18n) {
            throw new \RuntimeException(\sprintf('I18n %s not found', $name));
        }
        $id = $i18n->getId();
        $this->repository->delete($i18n);

        return \strval($id);
    }
}
