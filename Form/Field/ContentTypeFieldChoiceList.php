<?php
namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ContentTypeFieldChoiceList implements ChoiceListInterface
{
    private $choices;
    
    private $types;
    private $firstLevelOnly;
    private $mapping;
    
    public function __construct(array $mapping, array $types, $firstLevelOnly)
    {
        $this->choices = [];
        $this->types = $types;
        $this->firstLevelOnly = $firstLevelOnly;
        $this->mapping = $mapping;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getChoices()
    {
        $this->loadAll();
        return $this->choices;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return array_keys($this->choices);
    }
    
    /**
     * @return array
     */
    public function getStructuredValues()
    {
        $values = [];
        foreach ($this->choices as $key => $choice) {
            $values[$key] = $key;
        }
        return [$values];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOriginalKeys()
    {
        return $this->choices;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $choices)
    {
        return array_values($choices);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices)
    {
        return array_values($choices);
    }
    
    private function recursiveLoad($mapping, $path = '')
    {
        foreach ($mapping as $key => $field) {
            $newPath = (empty($path) ? '' : $path . '.') . $key;
            if (isset($field['type']) && array_search($field['type'], $this->types) !== false) {
                $this->choices[$newPath] = new ContentTypeFieldChoiceListItem($newPath, $newPath);
            }
            if (isset($field['fields'])) {
                foreach ($field['fields'] as $fieldName => $field) {
                    if (isset($field['type']) && array_search($field['type'], $this->types) !== false) {
                        $fieldPath = $newPath . '.' . $fieldName;
                        $this->choices[$fieldPath] = new ContentTypeFieldChoiceListItem($fieldPath, $fieldPath);
                    }
                }
            }
            if (isset($field['properties']) && !$this->firstLevelOnly) {
                $this->recursiveLoad($field['properties'], $newPath);
            }
        }
    }
    
    public function loadAll()
    {
        $this->choices[''] = new ContentTypeFieldChoiceListItem(null, 'Select a field if apply');
        $this->recursiveLoad($this->mapping);
        return $this->choices;
    }
    
    /**
     * intiate (or re-initiate) the choices array based on the list of key passed in parameter
     *
     * @param array $choices
     */
    public function loadChoices(array $choices)
    {
        
        
        foreach ($choices as $choice) {
            $path = explode('.', $choice);
            
            $target = $this->mapping;
            $value = '';
            $label = '';
            foreach ($path as $child) {
                if (isset($target[$child])) {
                    $value = (empty($value) ? '' : '.') . $child;
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
