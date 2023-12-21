<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class NumberFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Number field';
    }

    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-sort-by-order';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', TextType::class, [
                'label' => ($options['label'] ?? $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
        ]);
    }

    public function isValid(DataField &$dataField, DataField $parent = null, mixed &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = parent::isValid($dataField, $parent, $masterRawData);

        $rawData = $dataField->getRawData();
        if (!empty($rawData) && !\is_numeric($rawData)) {
            $isValid = false;
            $dataField->addMessage('Not a number');
        }

        return $isValid;
    }

    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It must be overrided.
             */
            $out[$data->giveFieldType()->getName()] = $data->getRawData();
        }
    }

    public function generateMapping(FieldType $current): array
    {
        return [
                $current->getName() => \array_merge(['type' => 'double'], \array_filter($current->getMappingOptions())),
        ];
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        //         // String specific display options
        //         $optionsForm->get ( 'displayOptions' )->add ( 'choices', TextareaType::class, [
        //                 'required' => false,
        //         ] )->add ( 'labels', TextareaType::class, [
        //                 'required' => false,
        //         ] );

        //         // String specific mapping options
        //         $optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
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
        $temp = $data['value'];

        $message = false;
        if (null !== $temp) {
            if (\is_numeric($temp)) {
                $temp = \doubleval($temp);
            } else {
                $message = 'It is not a float value:'.$temp;
            }
        }

        $out = parent::reverseViewTransform($temp, $fieldType);
        if ($message) {
            $out->addMessage($message);
        }

        return $out;
    }
}
