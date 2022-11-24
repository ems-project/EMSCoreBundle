<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Date Time Field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-calendar';
    }

    public function getBlockPrefix(): string
    {
        return 'date_time_field_type';
    }

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
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $builder->add('value', TextType::class, [
            'label' => (isset($options['label']) ? $options['label'] : $fieldType->getName()),
            'required' => false,
            'disabled' => $this->isDisabled($options),
            'attr' => [
                'class' => 'datetime-picker',
                'data-date-format' => $fieldType->getDisplayOption('displayFormat', 'D/MM/YYYY HH:mm:ss'),
                'data-date-days-of-week-disabled' => \sprintf('[%s]', $fieldType->getDisplayOption('daysOfWeekDisabled')),
                'data-date-disabled-hours' => \sprintf('[%s]', $fieldType->getDisplayOption('hoursDisabled')),
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [
            $current->getName() => \array_merge(
                ['type' => 'date', 'format' => 'date_time_no_millis'],
                \array_filter($current->getMappingOptions())
            ),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')
            ->add('displayFormat', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => '(JS) D/MM/YYYY HH:mm:ss'],
            ])
            ->add('parseFormat', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => '(PHP) d/m/Y H:i:s'],
            ])
            ->add('daysOfWeekDisabled', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'i.e. 0,6'],
            ])
            ->add('hoursDisabled', TextType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'i.e. 0,24'],
            ])
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        $data = parent::viewTransform($dataField);
        $value = null;

        if (\is_string($data) && '' !== $data) {
            $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $data);
            $value = $dateTime ? $dateTime->format(\DateTimeImmutable::ATOM) : null;
        }

        return ['value' => $value];
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $data
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $value = $data['value'];

        if (null === $value || '' === $value) {
            return parent::reverseViewTransform(null, $fieldType);
        }

        $parseFormat = $fieldType->getDisplayOption('parseFormat', 'd/m/Y H:i:s');
        $parseDateTime = \DateTimeImmutable::createFromFormat($parseFormat, $value);

        if ($parseDateTime) {
            return parent::reverseViewTransform($parseDateTime->format(\DateTimeImmutable::ATOM), $fieldType);
        }

        $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $value);

        if (false === $dateTime) {
            $dataField = parent::reverseViewTransform($value, $fieldType);
            $dataField->addMessage(\sprintf('Invalid parse format %s or ATOM for date string: %s', $parseFormat, $value));

            return $dataField;
        }

        return parent::reverseViewTransform($dateTime->format(\DateTimeImmutable::ATOM), $fieldType);
    }
}
