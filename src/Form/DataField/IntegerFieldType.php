<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class IntegerFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Integer field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-sort-by-order';
    }

    #[\Override]
    public function isValid(DataField &$dataField, ?DataField $parent = null, mixed &$masterRawData = null): bool
    {
        if ($this->hasDeletedParent($parent)) {
            return true;
        }

        $isValid = parent::isValid($dataField, $parent, $masterRawData);

        $rawData = $dataField->getRawData();
        if (!empty($rawData) && !\is_numeric($rawData)) {
            $isValid = false;
            $dataField->addMessage('Not a integer');
        }

        return $isValid;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', TextType::class, [
            'label' => ($options['label'] ?? $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'attr' => [
                // 'class' => 'spinner',
            ],
        ]);
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $out[$data->giveFieldType()->getName()] = $data->getIntegerValue();
        }
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => $this->elasticsearchService->updateMapping(\array_merge(['type' => 'integer'], \array_filter($current->getMappingOptions()))),
        ];
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('copy_to', TextType::class, [
                'required' => false,
            ]);
        }
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => $out];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $temp = $data['value'] ?? null;

        $message = false;
        if (null !== $temp) {
            if (\is_numeric($temp)) {
                $temp = \intval($temp);
            } else {
                $message = 'It is not a integer value';
            }
        }

        $out = parent::reverseViewTransform($temp, $fieldType);
        if ($message) {
            $out->addMessage($message);
        }

        return $out;
    }
}
