<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Date field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-calendar';
    }

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        if (empty($data)) {
            return parent::modelTransform([], $fieldType);
        }
        $dates = [];
        $format = $fieldType->getMappingOption('format', false);
        if (false !== $format) {
            $format = static::convertJavaDateFormat($format);
        } else {
            $format = \DateTimeInterface::ISO8601;
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
        $out->addMessage('Was not able to import:'.Json::encode($data));

        return $out;
    }

    /**
     * @return string[]|string|null
     */
    #[\Override]
    public function reverseModelTransform(DataField $dataField)
    {
        $data = parent::reverseModelTransform($dataField);
        $format = $dataField->giveFieldType()->getMappingOption('format', false);
        if (false !== $format) {
            $format = static::convertJavaDateFormat($format);
        } else {
            $format = \DateTime::ISO8601;
        }
        $out = [];
        if (\is_iterable($data) && !empty($data)) {
            foreach ($data as $item) {
                if ($item instanceof \DateTime) {
                    $out[] = $item->format($format);
                }
            }
        }
        if (!$dataField->giveFieldType()->getDisplayBoolOption('multidate', false)) {
            if (empty($out)) {
                return null;
            } else {
                return $out[0];
            }
        }

        return $out;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $data = parent::viewTransform($dataField);
        $out = [];
        $format = DateFieldType::convertJavascriptDateFormat($dataField->giveFieldType()->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        if (\is_iterable($data) && !empty($data)) {
            foreach ($data as $date) {
                if ($date) {
                    $out[] = $date->format($format);
                }
            }
        }
        $temp = ['value' => \implode(',', $out)];

        return $temp;
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dates = [];
        $format = DateFieldType::convertJavascriptDateFormat($fieldType->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        foreach (\explode(',', (string) $data['value']) as $date) {
            if (!empty($date)) {
                $dates[] = \DateTime::createFromFormat($format, $date);
            }
        }
        $dataField = parent::reverseViewTransform($dates, $fieldType);

        return $dataField;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'datefieldtype';
    }

    #[\Override]
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $format = $dataField->giveFieldType()->getMappingOptions()['format'];
            $format = DateFieldType::convertJavaDateFormat($format);

            if (null == $sourceArray) {
                $sourceArray = [];
            }
            if (\is_string($sourceArray)) {
                $sourceArray = [$sourceArray];
            }
            if (!\is_array($sourceArray)) {
                throw new \RuntimeException('Unexpected non-iterable source array');
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

        return [$dataField->giveFieldType()->getName()];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
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
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
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

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => \array_merge([
                'type' => 'date',
                'format' => 'date_time_no_millis',
            ], \array_filter($current->getMappingOptions())),
        ];
    }

    #[\Override]
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            $format = $data->giveFieldType()->getMappingOptions()['format'];
            $multidate = $data->giveFieldType()->getDisplayOptions()['multidate'];

            $format = DateFieldType::convertJavaDateFormat($format);
            $dataRawData = $data->getRawData();

            if ($multidate) {
                $dates = [];
                if (\is_array($dataRawData)) {
                    foreach ($dataRawData as $dataValue) {
                        $dateTime = DateTime::createFromFormat($dataValue, \DateTimeInterface::ISO8601);
                        $dates[] = $dateTime->format($format);
                    }
                }
            } else {
                $dates = null;
                if (\is_array($dataRawData) && (\count($dataRawData) >= 1)) {
                    $dateTime = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $dataRawData[0]);
                    if ($dateTime) {
                        $dates = $dateTime->format($format);
                    } else {
                        // TODO: at least a warning
                        $dates = null;
                    }
                }
            }

            $out[$data->giveFieldType()->getName()] = $dates;
        }
    }

    public static function convertJavaDateFormat(string $format): string
    {
        $dateFormat = $format;
        // TODO: naive approch....find a way to comvert java date format into php
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

    public static function convertJavascriptDateFormat(string $format): string
    {
        $dateFormat = $format;
        // see https://bootstrap-datepicker.readthedocs.io/en/latest/options.html#format
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

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('format', TextType::class, [
                'required' => false,
                'empty_data' => 'yyyy/MM/dd',
                'attr' => ['placeholder' => 'i.e. yyyy/MM/dd'],
            ])
            ->add('copy_to', TextType::class, [
                'required' => false,
            ]);
        }

        // String specific display options
        $optionsForm->get('displayOptions')->add('displayFormat', TextType::class, [
            'required' => false,
            'empty_data' => 'dd/MM/yyyy',
            'attr' => [
                'placeholder' => 'e.g. dd/MM/yyyy',
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
            'label' => 'Today highlight (deprecated)',
        ]);
        $optionsForm->get('displayOptions')->add('multidate', CheckboxType::class, [
            'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekDisabled', TextType::class, [
            'required' => false,
            'attr' => [
                'placeholder' => 'e.g. [0,6]',
            ],
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekHighlighted', TextType::class, [
            'required' => false,
            'label' => 'Days of week highlighted (deprecated)',
            'attr' => [
                'placeholder' => 'i.e. 0,6',
            ],
        ]);
    }
}
