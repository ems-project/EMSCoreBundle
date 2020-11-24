<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class IconFieldType extends DataFieldType
{
    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-flag';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Icon field';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('value', IconPickerType::class, [
                'label' => (null != $options['label'] ? $options['label'] : 'Icon field type'),
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
     */
    public function getBlockPrefix()
    {
        return 'bypassdatafield';
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => $out];
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $value = $data['value'];

        return parent::reverseViewTransform($value, $fieldType);
    }
}
