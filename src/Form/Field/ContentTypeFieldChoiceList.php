<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ContentTypeFieldChoiceList implements ChoiceListInterface
{
    /** @var array<mixed> */
    private array $choices = [];

    /**
     * @param array<mixed> $mapping
     * @param array<mixed> $types
     */
    public function __construct(private readonly array $mapping, private readonly array $types, private readonly bool $firstLevelOnly)
    {
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getChoices(): array
    {
        $this->loadAll();

        return $this->choices;
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getValues(): array
    {
        return \array_keys($this->choices);
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getStructuredValues(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return [$values];
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getOriginalKeys(): array
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }

        return $values;
    }

    /**
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getChoicesForValues(array $choices): array
    {
        return \array_values($choices);
    }

    /**
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getValuesForChoices(array $choices): array
    {
        return \array_values($choices);
    }

    /**
     * @param array<mixed> $mapping
     */
    private function recursiveLoad(array $mapping, ?string $path = null): void
    {
        foreach ($mapping as $key => $field) {
            $newPath = (null === $path ? '' : $path.'.').$key;
            if (isset($field['type']) && \in_array($field['type'], $this->types)) {
                $this->choices[$newPath] = new ContentTypeFieldChoiceListItem($newPath, $newPath);
            }
            if (isset($field['fields'])) {
                foreach ($field['fields'] as $fieldName => $childField) {
                    if (isset($childField['type']) && \in_array($childField['type'], $this->types)) {
                        $fieldPath = $newPath.'.'.$fieldName;
                        $this->choices[$fieldPath] = new ContentTypeFieldChoiceListItem($fieldPath, $fieldPath);
                    }
                }
            }
            if (isset($field['properties']) && !$this->firstLevelOnly) {
                $this->recursiveLoad($field['properties'], $newPath);
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function loadAll(): array
    {
        $this->choices[''] = new ContentTypeFieldChoiceListItem(null, 'Select a field if apply');
        $this->recursiveLoad($this->mapping);

        return $this->choices;
    }

    /**
     * intiate (or re-initiate) the choices array based on the list of key passed in parameter.
     *
     * @param array<mixed> $choices
     *
     * @return array<mixed>
     */
    public function loadChoices(array $choices): array
    {
        foreach ($choices as $choice) {
            $path = \explode('.', (string) $choice);

            $target = $this->mapping;
            $value = '';
            $label = '';
            foreach ($path as $child) {
                if (isset($target[$child])) {
                    $value = (empty($value) ? '' : '.').$child;
                    $label = $child;
                } else {
                    break;
                }
            }
            if (!empty($value)) {
                $this->choices[$value] = new ContentTypeFieldChoiceListItem($value, $label);
            }
        }

        return $this->choices;
    }
}
