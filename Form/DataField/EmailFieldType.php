<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
                                
/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class EmailFieldType extends DataFieldType
{

    
    /**
     * Get a icon to visually identify a FieldType
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-envelope';
    }
    
    public function getBlockPrefix()
    {
        return 'bypassdatafield';
    }
    
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel()
    {
        return 'Email field';
    }


    /**
     *
     * {@inheritdoc}
     *
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);
    
        $out['mappingOptions']['index'] = 'not_analyzed';
    
        return $out;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function isValid(DataField &$dataField, DataField $parent = null, &$masterRawData = null)
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = parent::isValid($dataField, $parent, $masterRawData);
        
        $rawData = $dataField->getRawData();
        if (! empty($rawData) && filter_var($rawData, FILTER_VALIDATE_EMAIL) === false) {
            $isValid = false;
            $dataField->addMessage("Not a valid email address");
        }
        
        return $isValid;
    }
    
    /**
     * {@inheritDoc}
     */
    public function modelTransform(array $data, FieldType $fieldType): DataField
    {
        if (empty($data)) {
            return parent::modelTransform(null, $fieldType);
        }
        if (is_string($data)) {
            return parent::modelTransform($data, $fieldType);
        }
        $out = parent::modelTransform(null, $fieldType);
        $out->addMessage('ems was not able to import the data: '.json_encode($data));
        return $out;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        return ['value' => parent::viewTransform($dataField)];
    }
    
    /**
     * {@inheritDoc}
     */
    public function reverseViewTransform(array $data, FieldType $fieldType): DataField
    {
        return parent::reverseViewTransform($data['value'], $fieldType);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $options ['metadata'];
        $builder->add('value', TextType::class, [
                'label' => (null != $options ['label']?$options ['label']:'Email field type'),
                'disabled'=> $this->isDisabled($options),
                'required' => false,
        ]);
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific mapping options
        $optionsForm->get('mappingOptions')
            ->add('analyzer', AnalyzerPickerType::class)
            ->add('copy_to', TextType::class, [
                'required' => false,
            ]);
    }
}
