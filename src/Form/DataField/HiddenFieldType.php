<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class HiddenFieldType extends DataFieldType
{
    // TODO: deorecated class?

    public function getLabel(): string
    {
        throw new \Exception('This Field Type should not be used as field (as service)');
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', HiddenType::class, [
                    'disabled' => $this->isDisabled($options),
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'empty';
    }

    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => \json_encode($out, JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = \json_decode((string) $data['value'], null, 512, JSON_THROW_ON_ERROR);
            $dataField->setRawData($value);
        } catch (\Exception) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }

        return $dataField;
    }
}
