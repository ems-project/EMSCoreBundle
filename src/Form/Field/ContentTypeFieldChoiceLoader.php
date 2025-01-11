<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ContentTypeFieldChoiceLoader implements ChoiceLoaderInterface
{
    private readonly ContentTypeFieldChoiceList $contentTypeFieldChoiceList;

    /**
     * @param array<mixed> $mapping
     * @param array<mixed> $types
     */
    public function __construct(array $mapping, array $types, bool $firstLevelOnly)
    {
        $this->contentTypeFieldChoiceList = new ContentTypeFieldChoiceList($mapping, $types, $firstLevelOnly);
    }

    #[\Override]
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
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    #[\Override]
    public function loadChoicesForValues(array $values, $value = null): array
    {
        $this->contentTypeFieldChoiceList->loadChoices($values);

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
        $this->contentTypeFieldChoiceList->loadChoices($choices);

        return $choices;
    }
}
