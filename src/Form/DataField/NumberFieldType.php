<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class NumberFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Number field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'glyphicon glyphicon-sort-by-order';
    }

//     /**
//      *
//      * {@inheritdoc}
//      *
//      */
//     public function importData(DataField $dataField, $sourceArray, $isMigration) {
//         $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
//         if(!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
//             $dataField->setFloatValue($sourceArray);
//         }
//         return [$dataField->getFieldType()->getName()];
//     }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', TextType::class, [
                'label' => (isset($options['label']) ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(DataField &$dataField, DataField $parent = null, &$masterRawData = null)
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

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            /*
             * by default it serialize the text value.
             * It must be overrided.
             */
            $out[$data->getFieldType()->getName()] = $data->getRawData();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        return [
                $current->getName() => \array_merge(['type' => 'double'], \array_filter($current->getMappingOptions())),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
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
