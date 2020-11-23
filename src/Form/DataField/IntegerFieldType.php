<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class IntegerFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Integer field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'glyphicon glyphicon-sort-by-order';
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
            $dataField->addMessage('Not a integer');
        }

        return $isValid;
    }

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
                'attr' => [
                        //'class' => 'spinner',
                ],
        ]);
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
            $out[$data->getFieldType()->getName()] = $data->getIntegerValue();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current, $withPipeline)
    {
        return [
                $current->getName() => $this->elasticsearchService->updateMapping(\array_merge(['type' => 'integer'], \array_filter($current->getMappingOptions()))),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('mappingOptions')->add('copy_to', TextType::class, [
            'required' => false,
        ]);

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

    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $temp = $data['value'];

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
