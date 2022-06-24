<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ContentTypeFieldChoiceLoader implements ChoiceLoaderInterface
{
    private ContentTypeFieldChoiceList $contentTypeFieldChoiceList;

    /**
     * @param array<mixed> $mapping
     * @param array<mixed> $types
     */
    public function __construct(array $mapping, array $types, bool $firstLevelOnly)
    {
        $this->contentTypeFieldChoiceList = new ContentTypeFieldChoiceList($mapping, $types, $firstLevelOnly);
    }

    /**
     * {@inheritDoc}
     */
    public function loadChoiceList($value = null): ContentTypeFieldChoiceList
    {
        return $this->contentTypeFieldChoiceList;
    }

    /**
     * @return array<mixed>
     */
    public function loadAll(): array
    {
        return $this->contentTypeFieldChoiceList->loadAll();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    public function loadChoicesForValues(array $values, $value = null): array
    {
        $this->contentTypeFieldChoiceList->loadChoices($values);

        return $values;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function loadValuesForChoices(array $choices, $value = null): array
    {
        $this->contentTypeFieldChoiceList->loadChoices($choices);

        return $choices;
    }
}
