<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ObjectChoiceLoader implements ChoiceLoaderInterface
{
    
    /**@var ObjectChoiceList $objectChoiceList*/
    private $objectChoiceList;
    
    public function __construct(
        $objectChoiceCacheService,
        $types,
        bool $loadAll,
        bool $circleOnly,
        bool $withWarning
    ) {
        $this->objectChoiceList = new ObjectChoiceList($objectChoiceCacheService, $types, $loadAll, $circleOnly, $withWarning);
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
        return $this->objectChoiceList->loadAll($this->objectChoiceList->getTypes());
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
