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
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Checkbox field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'glyphicon glyphicon-check';
    }

    /**
     * {@inheritdoc}
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $dataField->setBooleanValue($sourceArray ? true : false);
        }

        return [$dataField->getFieldType()->getName()];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', CheckboxType::class, [
                'label' => ($options['question_label'] ? $options['question_label'] : (isset($options['label']) ? $options['label'] : false)),
                'disabled' => $this->isDisabled($options),
                'required' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function modelTransform($data, FieldType $fieldType)
    {
        $dataField = new DataField();
        $dataField->setRawData(\boolval($data));
        $dataField->setFieldType($fieldType);

        return $dataField;
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        return ['value' => ((null !== $out && !empty($out) && $out) ? true : false)];
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::configureOptions()
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
                'question_label' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $out = parent::reverseViewTransform($data, $fieldType);
        $value = false;
        if (isset($data['value']) && true === $data['value']) {
            $value = true;
        }
        $out->setRawData($value);

        return $out;
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out[$data->getFieldType()->getName()] = $data->getBooleanValue();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        return [
                $current->getName() => $this->elasticsearchService->updateMapping(\array_merge(['type' => 'boolean'], \array_filter($current->getMappingOptions()))),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
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
