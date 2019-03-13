<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ContentTypeFieldChoiceLoader implements ChoiceLoaderInterface
{
    
    public function __construct(array $mapping, array $types, $firstLevelOnly)
    {
        $this->contentTypeFieldChoiceList = new ContentTypeFieldChoiceList($mapping, $types, $firstLevelOnly);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        return $this->contentTypeFieldChoiceList;
    }


    /**
     * {@inheritdoc}
     */
    public function loadAll()
    {
        return $this->contentTypeFieldChoiceList->loadAll();
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        $this->contentTypeFieldChoiceList->loadChoices($values);
        return $values;
    }
    
    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        $this->contentTypeFieldChoiceList->loadChoices($choices);
        return $choices;
    }
}
