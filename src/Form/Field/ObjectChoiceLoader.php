<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ObjectChoiceLoader implements ChoiceLoaderInterface
{
    private ObjectChoiceList $objectChoiceList;

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        string $types,
        bool $loadAll,
        bool $circleOnly,
        bool $withWarning
    ) {
        $this->objectChoiceList = new ObjectChoiceList($objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null): ObjectChoiceList
    {
        return $this->objectChoiceList;
    }

    /**
     * @return array<mixed>
     */
    public function loadAll(): array
    {
        return $this->objectChoiceList->loadAll($this->objectChoiceList->getTypes());
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function loadChoicesForValues(array $values, $value = null): array
    {
        $this->objectChoiceList->loadChoices($values);

        return $values;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function loadValuesForChoices(array $choices, $value = null): array
    {
        $this->objectChoiceList->loadChoices($choices);

        return $choices;
    }
}
