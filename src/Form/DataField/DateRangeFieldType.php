<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\Options\SubOptionsType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangeFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Date range field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-calendar-o';
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        $options = $dataField->giveFieldType()->getOptions();
        $rawData = $dataField->getRawData();

        if (\is_array($rawData) && !empty($dataField->getRawData())) {
            $dateFrom = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $rawData[$options['mappingOptions']['fromDateMachineName']]);
            $dateTo = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $rawData[$options['mappingOptions']['toDateMachineName']]);

            if ($dateFrom && $dateTo) {
                $displayFormat = DateRangeFieldType::convertJavascriptDateRangeFormat($options['displayOptions']['locale']['format']);

                return ['value' => $dateFrom->format($displayFormat).' - '.$dateTo->format($displayFormat)];
            }
        }

        return ['value' => ''];
    }

    public function getBlockPrefix(): string
    {
        return 'bypassdatafield';
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::reverseViewTransform($data, $fieldType);
        $input = $data['value'];
        $options = $fieldType->getOptions();

        $format = DateRangeFieldType::convertJavascriptDateRangeFormat($options['displayOptions']['locale']['format']);

        $inputs = \explode(' - ', $input);

        if (2 == \count($inputs)) {
            $convertedDates = [];

            $fromConverted = \DateTime::createFromFormat($format, $inputs[0]);
            if ($fromConverted) {
                if (!$options['displayOptions']['timePicker']) {
                    $fromConverted->setTime(0, 0, 0);
                }
                $convertedDates[$options['mappingOptions']['fromDateMachineName']] = $fromConverted->format(\DateTime::ISO8601);
            }

            $toConverted = \DateTime::createFromFormat($format, $inputs[1]);
            if ($toConverted) {
                if (!$options['displayOptions']['timePicker']) {
                    $toConverted->setTime(23, 59, 59);
                }
                $convertedDates[$options['mappingOptions']['toDateMachineName']] = $toConverted->format(\DateTime::ISO8601);
            }

            $dataField->setRawData($convertedDates);
        } else {
            // TODO: log warnign
        }

        return $dataField;
    }

    /**
     * {@inheritDoc}
     */
    public static function filterSubField(array $data, array $option): array
    {
        if (!$option['mappingOptions']['nested']) {
            $range = [];
            $fromDateMachineName = $option['mappingOptions']['fromDateMachineName'];
            $toDateMachineName = $option['mappingOptions']['toDateMachineName'];
            if (isset($data[$fromDateMachineName])) {
                $range[$fromDateMachineName] = $data[$fromDateMachineName];
            }
            if (isset($data[$toDateMachineName])) {
                $range[$toDateMachineName] = $data[$toDateMachineName];
            }

            return $range;
        }

        return parent::filterSubField($data, $option);
    }

    /**
     * {@inheritDoc}
     */
    public static function isVirtual(array $option = []): bool
    {
        if (!isset($option['mappingOptions'])) {
            return false;
        }

        $nested = $option['mappingOptions']['nested'] ?? false;

        return !$nested;
    }

    /**
     * {@inheritDoc}
     */
    public function importData(DataField $dataField, $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $mappingOptions = $dataField->giveFieldType()->getMappingOptions();

            if (!$mappingOptions['nested']) {
                $out = [];
                $in = [];
                if (isset($sourceArray[$mappingOptions['fromDateMachineName']])) {
                    $out[] = $mappingOptions['fromDateMachineName'];
                    $in[$mappingOptions['fromDateMachineName']] = $sourceArray[$mappingOptions['fromDateMachineName']];
                }
                if (isset($sourceArray[$mappingOptions['toDateMachineName']])) {
                    $out[] = $mappingOptions['toDateMachineName'];
                    $in[$mappingOptions['toDateMachineName']] = $sourceArray[$mappingOptions['toDateMachineName']];
                }
                $dataField->setRawData($in);

                return $out;
            } else {
                return parent::importData($dataField, $sourceArray, $isMigration);
            }
        }

        return [$dataField->giveFieldType()->getName()];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('showWeekNumbers', false);
        $resolver->setDefault('timePicker', true);
        $resolver->setDefault('timePicker24Hour', true);
        $resolver->setDefault('timePickerIncrement', 5);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('locale', []);
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', IconTextType::class, [
                'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
                'icon' => $options['icon'],
                'attr' => [
                    'class' => 'ems_daterangepicker',
                    'data-display-option' => \json_encode($fieldType->getDisplayOptions()),
                ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['toDateMachineName'] = $name.'_to_date';
        $out['mappingOptions']['fromDateMachineName'] = $name.'_from_date';
        $out['mappingOptions']['nested'] = true;
        $out['mappingOptions']['index'] = null;
        $out['displayOptions']['timePickerIncrement'] = 5;
        $out['displayOptions']['locale'] = [
                'format' => 'DD/MM/YYYY HH:mm',
                'firstDay' => 1,
        ];

        return $out;
    }

    public static function convertJavascriptDateRangeFormat(string $format): string
    {
        $dateFormat = $format;
        // see http://www.daterangepicker.com/#examples
        $dateFormat = \str_replace('DD', 'd', $dateFormat);
        $dateFormat = \str_replace('MM', 'm', $dateFormat);
        $dateFormat = \str_replace('YYYY', 'Y', $dateFormat);
        $dateFormat = \str_replace('YY', 'y', $dateFormat);
        $dateFormat = \str_replace('hh', 'h', $dateFormat);
        $dateFormat = \str_replace('HH', 'H', $dateFormat);
        $dateFormat = \str_replace('mm', 'i', $dateFormat);
        $dateFormat = \str_replace('ss', 's', $dateFormat);
        $dateFormat = \str_replace('aa', 'A', $dateFormat);

        return $dateFormat;
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        $out = [
            $current->getMappingOptions()['fromDateMachineName'] => [
                'type' => 'date',
                'format' => 'date_time_no_millis',
            ],
            $current->getMappingOptions()['toDateMachineName'] => [
                'type' => 'date',
                'format' => 'date_time_no_millis',
            ],
        ];

        if (!empty($current->getMappingOptions()['index'])) {
            $out[$current->getMappingOptions()['fromDateMachineName']]['index'] = $current->getMappingOptions()['index'];
            $out[$current->getMappingOptions()['toDateMachineName']]['index'] = $current->getMappingOptions()['index'];
        }

        if ($current->getMappingOptions()['nested']) {
            $out = [
                $current->getName() => [
                    'type' => 'nested',
                    'properties' => $out,
                ],
            ];
        }

        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function buildObjectArray(DataField $data, array &$out): void
    {
        if (!$data->giveFieldType()->getDeleted()) {
            if ($data->giveFieldType()->getMappingOptions()['nested']) {
                $out[$data->giveFieldType()->getName()] = $data->getRawData();
            } else {
                $rawData = $data->getRawData();
                if (!\is_array($rawData) || empty($rawData)) {
                    $rawData = [];
                }

                $out = \array_merge($out, $rawData);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm
                ->get('mappingOptions')
                ->add('fromDateMachineName', TextType::class, ['required' => false])
                ->add('toDateMachineName', TextType::class, ['required' => false])
                ->add('nested', CheckboxType::class, ['required' => false]);
        }

        $optionsForm->get('displayOptions')->add('locale', SubOptionsType::class, [
                'required' => false,
                'label' => false,
        ]);
        $optionsForm->get('displayOptions')->get('locale')->add('format', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'i.e. DD/MM/YYYY HH:mm',
                ],
        ]);
        $optionsForm->get('displayOptions')->get('locale')->add('firstDay', IntegerType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('showWeekNumbers', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('timePicker', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('timePicker24Hour', CheckboxType::class, [
                'required' => false,
        ]);

        $optionsForm->get('displayOptions')->add('timePickerIncrement', IntegerType::class, [
                'required' => false,
                'empty_data' => 5,
                'attr' => [
                    'placeholder' => '5',
                ],
        ]);
    }
}
