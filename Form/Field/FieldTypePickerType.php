<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldTypePickerType extends SelectPickerType
{
    
    private $dataFieldTypes;
    
    public function __construct()
    {
        parent::__construct();
        $this->dataFieldTypes = array();
    }
    
    public function addDataFieldType($dataField)
    {
        $this->dataFieldTypes[ get_class($dataField) ] = $dataField;
    }
    
    public function getDataFieldType($dataFieldTypeId)
    {
        return $this->dataFieldTypes[$dataFieldTypeId];
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        
        $resolver->setDefaults(array(
            'choices' => array_keys($this->dataFieldTypes),
            'attr' => [
                    'data-live-search' => true
            ],
            'choice_attr' => function ($category, $key, $index) {
                /** @var \EMS\CoreBundle\Form\DataField\DataFieldType $dataFieldType */
                $dataFieldType = $this->dataFieldTypes[$index];
                return [
                        'data-content' => '<div class="text-' . $category . '"><i class="' . $dataFieldType->getIcon() . '"></i>&nbsp;&nbsp;' . $dataFieldType->getLabel() . '</div>'
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ));
    }
}
