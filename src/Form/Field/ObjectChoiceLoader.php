<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ObjectChoiceLoader implements ChoiceLoaderInterface
{
    private readonly ObjectChoiceList $objectChoiceList;

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        string $types,
        bool $loadAll,
        bool $circleOnly,
        bool $withWarning,
        ?string $querySearchName,
    ) {
        $this->objectChoiceList = new ObjectChoiceList($objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning, $querySearchName);
    }

    #[\Override]
    public function loadChoiceList($value = null): ObjectChoiceList
    {
        return $this->objectChoiceList;
    }

    /**
     * @return array<mixed>
     */
    public function loadAll(): array
    {
        return $this->objectChoiceList->loadAll();
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    #[\Override]
    public function loadChoicesForValues(array $values, $value = null): array
    {
        $this->objectChoiceList->loadChoices($values);

        return $values;
    }

    /**
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    #[\Override]
    public function loadValuesForChoices(array $choices, $value = null): array
    {
        $this->objectChoiceList->loadChoices($choices);

        return $choices;
    }
}
