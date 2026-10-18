<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ChoiceList;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\ChoiceList;
use EMS\CoreBundle\Repository\ChoiceListRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;

class ChoiceListManager implements EntityServiceInterface
{
    public function __construct(private readonly ChoiceListRepository $choiceListRepository)
    {
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    /**
     * @return ChoiceList[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->choiceListRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'choice_list';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'choice_lists',
            'ChoiceList',
            'ChoiceLists',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->choiceListRepository->counter($searchValue);
    }

    public function update(ChoiceList $choiceList): void
    {
        $choiceList->setName(new Encoder()->slug(text: $choiceList->getName(), separator: '_')->toString());
        $this->choiceListRepository->update($choiceList);
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->choiceListRepository->getByIds($ids) as $choiceList) {
            $this->delete($choiceList);
        }
    }

    public function delete(ChoiceList $choiceList): void
    {
        $this->choiceListRepository->delete($choiceList);
    }

    public function getByName(string $name): ChoiceList
    {
        $choiceList = $this->choiceListRepository->getByName($name);
        if (null === $choiceList) {
            throw new \RuntimeException('Choice list not found');
        }

        return $choiceList;
    }

    public function existsByName(string $name): bool
    {
        $choiceList = $this->choiceListRepository->getByName($name);

        return null !== $choiceList;
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->choiceListRepository->getByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof ChoiceList) {
            throw new \RuntimeException('Unexpected choice list object');
        }

        $choiceList = ChoiceList::fromJson($json, $entity);
        $this->choiceListRepository->update($choiceList);

        return $choiceList;
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $choiceList = ChoiceList::fromJson($json);
        if (null !== $name && $choiceList->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Choice list name mismatched: %s vs %s', $choiceList->getName(), $name));
        }

        $this->choiceListRepository->create($choiceList);

        return $choiceList;
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $choiceList = $this->choiceListRepository->getByName($name);
        if (null === $choiceList) {
            throw new \RuntimeException(\sprintf('Choice list %s not found', $name));
        }

        $id = $choiceList->getId();
        $this->choiceListRepository->delete($choiceList);

        return $id;
    }
}
