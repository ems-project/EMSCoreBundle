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
    public static function getIcon(): string
    {
        return 'fa fa-flag';
    }

    public function getLabel(): string
    {
        return 'Icon field';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];
        $builder->add('value', IconPickerType::class, [
                'label' => (null != $options['label'] ? $options['label'] : 'Icon field type'),
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => $out];
    }

    /**
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = $data['value'];

        return parent::reverseViewTransform($value, $fieldType);
    }
}
