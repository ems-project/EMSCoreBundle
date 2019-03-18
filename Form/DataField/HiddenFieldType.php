<?php

namespace EMS\CoreBundle\Form\DataField;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
                        
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class HiddenFieldType extends DataFieldType
{
    //TODO: deorecated class?
     
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        throw new \Exception('This Field Type should not be used as field (as service)');
    }
     
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
            $builder->add('value', HiddenType::class, [
                    'disabled'=> $this->isDisabled($options),
            ]);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
     */
    public function getBlockPrefix()
    {
        return 'empty';
    }

    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);
        return ['value' => json_encode($out)];
    }
    
    /**
     * {@inheritDoc}
     */
    public function reverseViewTransform(array $data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = json_decode($data['value']);
            $dataField->setRawData($value);
        } catch (\Exception $e) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }
        return $dataField;
    }
}
