<?php

declare(strict_types=1);

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
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class DateFieldType extends DataFieldType
{
    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return ['type' => 'string', 'format' => 'date'];
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.date', [], 'emsco-core');
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
        $format = false !== $format ? DateTime::convertFormat('java', $format) : \DateTimeInterface::ATOM;
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
        $format = false !== $format ? DateTime::convertFormat('java', $format) : \DateTimeInterface::ATOM;

        $out = [];
        if (\is_iterable($data) && [] !== $data) {
            foreach ($data as $item) {
                if ($item instanceof \DateTime) {
                    $out[] = $item->format($format);
                }
            }
        }
        if (!$dataField->giveFieldType()->getDisplayBoolOption('multidate', false)) {
            if ([] === $out) {
                return null;
            }

            return $out[0];
        }

        return $out;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $data = parent::viewTransform($dataField);
        $out = [];
        $format = DateTime::convertFormat('js', $dataField->giveFieldType()->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        if (\is_iterable($data) && [] !== $data) {
            foreach ($data as $date) {
                if ($date) {
                    $out[] = $date->format($format);
                }
            }
        }

        return ['value' => \implode(',', $out)];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $dates = [];
        $format = DateTime::convertFormat('js', $fieldType->getDisplayOption('displayFormat', 'dd/mm/yyyy'));
        foreach (\explode(',', (string) $data['value']) as $date) {
            if (!empty($date)) {
                $dates[] = \DateTime::createFromFormat($format, $date);
            }
        }

        return parent::reverseViewTransform($dates, $fieldType);
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
        if (!$isMigration || [] === $migrationOptions || !$migrationOptions['protected']) {
            $format = DateTime::convertFormat('java', $dataField->giveFieldType()->getMappingOption('format'));

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
            foreach ($sourceArray as $child) {
                $dateObject = \DateTime::createFromFormat($format, $child);
                if ($dateObject) {
                    $data[] = $dateObject->format(\DateTimeInterface::ATOM);
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
            $format = DateTime::convertFormat('java', $data->giveFieldType()->getMappingOption('format'));
            $multidate = $data->giveFieldType()->getDisplayOptions()['multidate'];

            $dataRawData = $data->getRawData();

            if ($multidate) {
                $dates = [];
                if (\is_array($dataRawData)) {
                    foreach ($dataRawData as $dataValue) {
                        $dateTime = DateTime::createFromFormat($dataValue);
                        $dates[] = $dateTime->format($format);
                    }
                }
            } else {
                $dates = null;
                if (\is_array($dataRawData) && (\count($dataRawData) >= 1)) {
                    $dateTime = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $dataRawData[0]);
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

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('format', TextType::class, [
                'label' => t('field.format', [], 'emsco-core'),
                'required' => false,
                'empty_data' => 'yyyy/MM/dd',
                'attr' => ['placeholder' => t('placeholder.for_example', ['example' => 'yyyy/MM/dd'], 'emsco-core')],
            ])
            ->add('copy_to', TextType::class, [
                'label' => t('field.copy_to', [], 'emsco-core'),
                'required' => false,
            ]);
        }

        // String specific display options
        $optionsForm->get('displayOptions')->add('displayFormat', TextType::class, [
            'label' => t('field.display_format', [], 'emsco-core'),
            'required' => false,
            'empty_data' => 'dd/MM/yyyy',
            'attr' => [
                'placeholder' => t('placeholder.for_example', ['example' => 'dd/MM/yyyy'], 'emsco-core'),
            ],
        ]);
        $optionsForm->get('displayOptions')->add('weekStart', IntegerType::class, [
            'label' => t('field.week_start', [], 'emsco-core'),
            'required' => false,
            'empty_data' => 0,
            'attr' => [
                'placeholder' => t('placeholder.for_example', ['example' => '0'], 'emsco-core'),
            ],
        ]);
        $optionsForm->get('displayOptions')->add('todayHighlight', CheckboxType::class, [
            'label' => t('field.today_highlight', [], 'emsco-core'),
            'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('multidate', CheckboxType::class, [
            'label' => t('field.multiple', [], 'emsco-core'),
            'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekDisabled', TextType::class, [
            'label' => t('field.days_of_week_disabled', [], 'emsco-core'),
            'required' => false,
            'attr' => [
                'placeholder' => t('placeholder.for_example', ['example' => '0,6'], 'emsco-core'),
            ],
        ]);
        $optionsForm->get('displayOptions')->add('daysOfWeekHighlighted', TextType::class, [
            'label' => t('field.days_of_week_highlighted', [], 'emsco-core'),
            'required' => false,
            'attr' => [
                'placeholder' => t('placeholder.for_example', ['example' => '0,6'], 'emsco-core'),
            ],
        ]);
    }
}
