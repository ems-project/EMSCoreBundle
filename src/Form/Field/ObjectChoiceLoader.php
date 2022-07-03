<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ObjectChoiceLoader implements ChoiceLoaderInterface
{
    /** @var ObjectChoiceList */
    private $objectChoiceList;

    public function __construct(
        ObjectChoiceCacheService $objectChoiceCacheService,
        $types,
        bool $loadAll,
        bool $circleOnly,
        bool $withWarning,
        ?string $querySearchName
    ) {
        $this->objectChoiceList = new ObjectChoiceList($objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning, $querySearchName);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        return $this->objectChoiceList;
    }

    /**
     * {@inheritdoc}
     */
    public function loadAll()
    {
        return $this->objectChoiceList->loadAll();
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        $this->objectChoiceList->loadChoices($values);

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        $this->objectChoiceList->loadChoices($choices);

        return $choices;
    }
}
