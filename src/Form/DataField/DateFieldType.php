<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Date field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'fa fa-calendar';
    }

    public function modelTransform($data, FieldType $fieldType)
    {
        if (empty($data)) {
            return parent::modelTransform([], $fieldType);
        }
        $dates = [];
        $format = $fieldType->getMappingOption('format', false);
        if (false !== $format) {
            $format = $this->convertJavaDateFormat($format);
        } else {
            $format = \DateTime::ISO8601;
        }
        if (\is_string($data)) {
            $dates[] = \DateTime::createFromFormat($format, $data);

            return parent::modelTransform($dates, $fieldType);
        }
        if (\is_array($data)) {
            foreach ($data as $dataValue) {
                $dates[] = \DateTime::createFromFormat($format, $dataValue);
            }

            return parent::modelTransform($dates, $fieldType);
        }
        $out = parent::modelTransform(null, $fieldType);
        $out->addMessage('Was not able to import:'.\json_encode($data));

        return $out;
    }

    public function reverseModelTransform(DataField $dataField)
    {
        $data = parent::reverseModelTransform($dataField);
        $format = $dataField->getFieldType()->getMappingOption('format', false);
        if (false !== $format) {
            $format = $this->convertJavaDateFormat($format);
        } else {
            $format = \DateTime::ISO8601;
        }
        $out = [];
        if (!empty($data)) {
            foreach ($data as $data) {
                if ($data) {
                    $out[] = $data->format($format);
                }
            }
        }
        if (!$dataField->getFieldType()->getDisplayOptions()['multidate']) {
            if (empty($out)) {
                return null;
            } else {
                return $out[0];
            }
        }

        return $out;
    }

    public function viewTransform(DataField $dataField)
    {
        $data = parent::viewTransform($dataField);
        $out = [];
        $format = DateFieldType::convertJavascriptDateFormat($dataField->getFieldType()->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        if (!empty($data)) {
            foreach ($data as $date) {
                if ($date) {
                    $out[] = $date->format($format);
                }
            }
        }
        $temp = ['value' => \implode(',', $out)];

        return $temp;
    }

    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $dates = [];
        $format = DateFieldType::convertJavascriptDateFormat($fieldType->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        foreach (\explode(',', $data['value']) as $date) {
            if (!empty($date)) {
                $dates[] = \DateTime::createFromFormat($format, $date);
            }
        }
        $dataField = parent::reverseViewTransform($dates, $fieldType);

        return $dataField;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'datefieldtype';
    }

    /**
     * {@inheritdoc}
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration)
    {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $format = $dataField->getFieldType()->getMappingOptions()['format'];
            $format = DateFieldType::convertJavaDateFormat($format);

            if (null == $sourceArray) {
                $sourceArray = [];
            }
            if (\is_string($sourceArray)) {
                $sourceArray = [$sourceArray];
            }
            $data = [];
            foreach ($sourceArray as $idx => $child) {
                $dateObject = \DateTime::createFromFormat($format, $child);
                if ($dateObject) {
                    $data[] = $dateObject->format(\DateTime::ISO8601);
                } else {
                    $dataField->addMessage('Bad date format:'.$child);
                }
            }
            $dataField->setRawData($data);
        }

        return [$dataField->getFieldType()->getName()];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('displayFormat', 'dd/mm/yyyy');
        $resolver->setDefault('todayHighlight', false);
        $resolver->setDefault('weekStart', 1);
        $resolver->setDefault('daysOfWeekHighlighted', '');
        $resolver->setDefault('daysOfWeekDisabled', '');
        $resolver->setDefault('multidate', '');
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
                    'class' => 'datepicker',
                    'data-date-format' => $fieldType->getDisplayOptions()['displayFormat'],
                    'data-today-highlight' => $fieldType->getDisplayOptions()['todayHighlight'],
                    'data-week-start' => $fieldType->getDisplayOptions()['weekStart'],
                    'data-days-of-week-highlighted' => $fieldType->getDisplayOptions()['daysOfWeekHighlighted'],
                    'data-days-of-week-disabled' => $fieldType->getDisplayOptions()['daysOfWeekDisabled'],
                    'data-multidate' => $fieldType->getDisplayOptions()['multidate'] ? 'true' : 'false',
                ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current, $withPipeline)
    {
        return [
                $current->getName() => \array_merge([
                        'type' => 'date',
                        'format' => 'date_time_no_millis',
                ], \array_filter($current->getMappingOptions())),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildObjectArray(DataField $data, array &$out)
    {
        if (!$data->getFieldType()->getDeleted()) {
            $format = $data->getFieldType()->getMappingOptions()['format'];
            $multidate = $data->getFieldType()->getDisplayOptions()['multidate'];

            $format = DateFieldType::convertJavaDateFormat($format);

            if ($multidate) {
                $dates = [];
                if (null !== $data->getRawData()) {
                    foreach ($data->getRawData() as $dataValue) {
                        /** @var \DateTime $converted */
                        $dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $dataValue);
                        $dates[] = $dateTime->format($format);
                    }
                }
            } else {
                $dates = null;
                if (null !== $data->getRawData() && \count($data->getRawData()) >= 1) {
                    /** @var \DateTime $converted */
                    $dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $data->getRawData()[0]);
                    if ($dateTime) {
                        $dates = $dateTime->format($format);
                    } else {
                        //TODO: at least a warning
                        $dates = null;
                    }
                }
            }

            $out[$data->getFieldType()->getName()] = $dates;
        }
    }

    public static function convertJavaDateFormat($format)
    {
        $dateFormat = $format;
        //TODO: naive approch....find a way to comvert java date format into php
        $dateFormat = \str_replace('dd', 'd', $dateFormat);
        $dateFormat = \str_replace('MM', 'm', $dateFormat);
        $dateFormat = \str_replace('yyyy', 'Y', $dateFormat);
        $dateFormat = \str_replace('hh', 'g', $dateFormat);
        $dateFormat = \str_replace('HH', 'G', $dateFormat);
        $dateFormat = \str_replace('mm', 'i', $dateFormat);
        $dateFormat = \str_replace('ss', 's', $dateFormat);
        $dateFormat = \str_replace('aa', 'A', $dateFormat);

        return $dateFormat;
    }

    public static function convertJavascriptDateFormat($format)
    {
        $dateFormat = $format;
        //see https://bootstrap-datepicker.readthedocs.io/en/latest/options.html#format
        $dateFormat = \str_replace('yyyy', 'Y', $dateFormat);
        $dateFormat = \str_replace('yy', 'y', $dateFormat);
        $dateFormat = \str_replace('DD', 'l', $dateFormat);
        $dateFormat = \str_replace('D', 'D', $dateFormat);
        $dateFormat = \str_replace('dd', 'd', $dateFormat);
        //         $dateFormat = str_replace('d', 't', $dateFormat);
        $dateFormat = \str_replace('mm', 'm', $dateFormat);
        //         $dateFormat = str_replace('m', 'n', $dateFormat);
        $dateFormat = \str_replace('MM', 'F', $dateFormat);
        $dateFormat = \str_replace('M', 'M', $dateFormat);

        return $dateFormat;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('format', TextType::class, [
                'required' => false,
                'empty_data' => 'yyyy/MM/dd',
                'attr' => ['placeholder' => 'i.e. yyyy/MM/dd'],
            ]);
        }

        // String specific display options
        $optionsForm->get('displayOptions')->add('displayFormat', TextType::class, [
                'required' => false,
                'empty_data' => 'dd/mm/yyyy',
                'attr' => [
                    'placeholder' => 'i.e. dd/mm/yyyy',
                ],
        ]);
        $optionsForm->get('displayOptions')->add('weekStart', IntegerType::class, [
                'required' => false,
                'empty_data' => 0,
                'attr' => [
                    'placeholder' => '0',
                ],
        ]);
        $optionsForm->get('displayOptions')->add('todayHighlight', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('multidate', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekDisabled', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'i.e. 0,6',
                ],
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekHighlighted', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'i.e. 0,6',
                ],
        ]);
    }
}
