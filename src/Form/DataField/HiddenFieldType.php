<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\Helpers\Standard\Json;
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

        return ['value' => Json::encode($out)];
    }

    /**
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        try {
            $value = Json::mixedDecode((string) $data['value']);
            $dataField->setRawData($value);
        } catch (\Exception) {
            $dataField->setRawData(null);
            $dataField->addMessage('ems was not able to parse the field');
        }

        return $dataField;
    }
}
