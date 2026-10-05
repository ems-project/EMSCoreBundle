<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class DateTimeFieldType extends DataFieldType
{
    public const string DEFAULT_PARSE_FORMAT = 'd/m/Y H:i:s';
    public const string DEFAULT_DISPLAY_FORMAT = 'D/MM/YYYY HH:mm:ss';

    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return ['type' => 'string', 'format' => 'date-time'];
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.date_time', [], 'emsco-core');
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-calendar';
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'date_time_field_type';
    }

    /**
     * @param callable(FieldType, mixed): mixed $buildChildValue
     */
    #[\Override]
    public function buildMcpRawDataValue(FieldType $fieldType, mixed $rawData, callable $buildChildValue): mixed
    {
        return $this->normalizeMcpDateTimeValue($rawData);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'displayFormat' => 'dd/mm/yyyy',
            'parseFormat' => false,
            'daysOfWeekDisabled' => '',
            'hoursDisabled' => '',
        ]);
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
                'class' => 'datetime-picker',
                'data-date-format' => $fieldType->getDisplayOption('displayFormat', self::DEFAULT_DISPLAY_FORMAT),
                'data-date-days-of-week-disabled' => \sprintf('[%s]', $fieldType->getDisplayOption('daysOfWeekDisabled')),
                'data-date-disabled-hours' => \sprintf('[%s]', $fieldType->getDisplayOption('hoursDisabled')),
            ],
        ]);
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => \array_merge(
                ['type' => 'date', 'format' => 'date_time_no_millis'],
                \array_filter($current->getMappingOptions())
            ),
        ];
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add('displayFormat', TextType::class, [
                'label' => t('field.display_format', [], 'emsco-core'),
                'required' => false,
                'attr' => ['placeholder' => t('placeholder.for_example', ['example' => self::DEFAULT_DISPLAY_FORMAT], 'emsco-core')],
            ])
            ->add('parseFormat', TextType::class, [
                'label' => t('field.parse_format', [], 'emsco-core'),
                'required' => false,
                'attr' => ['placeholder' => t('placeholder.for_example', ['example' => \sprintf('%s (PHP)', self::DEFAULT_PARSE_FORMAT)], 'emsco-core')],
            ])
            ->add('daysOfWeekDisabled', TextType::class, [
                'label' => t('field.days_of_week_disabled', [], 'emsco-core'),
                'required' => false,
                'attr' => ['placeholder' => t('placeholder.for_example', ['example' => '0,6'], 'emsco-core')],
            ])
            ->add('hoursDisabled', TextType::class, [
                'label' => t('field.hours_disabled', [], 'emsco-core'),
                'required' => false,
                'attr' => ['placeholder' => t('placeholder.for_example', ['example' => '0,23'], 'emsco-core')],
            ])
        ;
    }

    #[\Override]
    public function viewTransform(DataField $dataField)
    {
        $data = parent::viewTransform($dataField);
        $value = null;

        if (\is_string($data) && '' !== $data) {
            $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $data);
            if ($dateTime instanceof \DateTimeInterface) {
                $fieldType = $dataField->getFieldType();
                $parseFormat = (null !== $fieldType) ? $fieldType->getDisplayOption('parseFormat') : null;
                $value = $dateTime->format($parseFormat ?? self::DEFAULT_PARSE_FORMAT);
            } else {
                $dataField->addMessage(\sprintf('Invalid date format. Expected format: ISO 8601. Received value: %s', $data));
                $value = $data;
            }
        }

        return ['value' => $value];
    }

    /**
     * @param array<mixed> $data
     */
    #[\Override]
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = $data['value'];

        if (null === $value || '' === $value) {
            return parent::reverseViewTransform(null, $fieldType);
        }

        $parseFormat = $fieldType->getDisplayOption('parseFormat', self::DEFAULT_PARSE_FORMAT);
        $parseDateTime = \DateTimeImmutable::createFromFormat($parseFormat, $value);

        if ($parseDateTime) {
            return parent::reverseViewTransform($parseDateTime->format(\DateTimeImmutable::ATOM), $fieldType);
        }

        $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $value);

        if (false === $dateTime) {
            $dataField = parent::reverseViewTransform($value, $fieldType);
            $dataField->addMessage(\sprintf('Invalid date format. Expected format: %s or ISO 8601. Received value: %s', $parseFormat, $value));

            return $dataField;
        }

        return parent::reverseViewTransform($dateTime->format(\DateTimeImmutable::ATOM), $fieldType);
    }
}
