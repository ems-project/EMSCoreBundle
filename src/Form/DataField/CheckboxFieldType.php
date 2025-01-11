<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckboxFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Checkbox field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'glyphicon glyphicon-check';
    }

    #[\Override]
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $dataField->setBooleanValue($sourceArray ? true : false);
        }

        return [$dataField->giveFieldType()->getName()];
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', CheckboxType::class, [
            'label' => ($options['question_label'] ?: $options['label'] ?? false),
            'disabled' => $this->isDisabled($options),
            'required' => false,
        ]);
    }

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $dataField = new DataField();
        $dataField->setRawData(\boolval($data));
        $dataField->setFieldType($fieldType);

        return $dataField;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => ((null !== $out && !empty($out)) ? true : false)];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'question_label' => false,
        ]);
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $out = parent::reverseViewTransform($data, $fieldType);
        $value = false;
        if (isset($data['value']) && true === $data['value']) {
            $value = true;
        }
        $out->setRawData($value);

        return $out;
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->giveFieldType()->getName()] = $data->getBooleanValue();
        }
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => $this->elasticsearchService->updateMapping(\array_merge(['type' => 'boolean'], \array_filter($current->getMappingOptions()))),
        ];
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('question_label', TextType::class, [
            'required' => false,
            //         ] )->add ( 'labels', TextareaType::class, [
            //                 'required' => false,
        ]);

        //         // String specific mapping options
        //         $optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
        $optionsForm->get('restrictionOptions')->remove('mandatory');
        $optionsForm->get('restrictionOptions')->remove('mandatory_if');
    }
}
