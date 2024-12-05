<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Basic content type for text (regular text input).
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TimeFieldType extends DataFieldType
{
    final public const STOREFORMAT = 'H:i:s';
    final public const INDEXFORMAT = 'HH:mm:ss';

    public function getLabel(): string
    {
        return 'Time field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-clock-o';
    }

    /**
     * {@inheritDoc}
     */
    public function importData(DataField $dataField, array|string|int|float|bool|null $sourceArray, bool $isMigration): array
    {
        $migrationOptions = $dataField->giveFieldType()->getMigrationOptions();
        if (!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $format = $dataField->giveFieldType()->getMappingOptions()['format'];
            $format = DateFieldType::convertJavaDateFormat($format);

            $timeObject = !\is_array($sourceArray) ? \DateTime::createFromFormat($format, \strval($sourceArray)) : false;
            if ($timeObject) {
                $dataField->setRawData($timeObject->format(\DateTimeInterface::ATOM));
            } else {
                $dataField->addMessage('Not able to parse the date');
            }
        }

        return [$dataField->giveFieldType()->getName()];
    }

    /**
     * Convert options into PHP date format string.
     *
     * @param array<string, mixed> $options
     */
    public static function getFormat(array $options): string
    {
        if ($options['displayOptions']['showMeridian']) {
            $format = 'g:i';
        } else {
            $format = 'G:i';
        }

        if ($options['displayOptions']['showSeconds']) {
            $format .= ':s';
        }

        if ($options['displayOptions']['showMeridian']) {
            $format .= ' A';
        }

        return $format;
    }

    /**
     * {@inheritDoc}
     */
    public function viewTransform(DataField $dataField)
    {
        $out = parent::viewTransform($dataField);

        if (\is_array($out)) {
            return '';
        }

        $format = static::getFormat($dataField->giveFieldType()->getOptions());

        $dateTime = \DateTime::createFromFormat(TimeFieldType::STOREFORMAT, \strval($out));
        if ($dateTime) {
            return $dateTime->format($format);
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function reverseViewTransform($data, FieldType $fieldType): DataField
    {
        $format = static::getFormat($fieldType->getOptions());
        $converted = !\is_array($data) ? \DateTime::createFromFormat($format, \strval($data)) : false;
        $convertedFromStoreFormat = !\is_array($data) ? \DateTime::createFromFormat($this::STOREFORMAT, \strval($data)) : false;
        if ($converted) {
            $out = $converted->format($this::STOREFORMAT);
        } elseif ($convertedFromStoreFormat) {
            $out = $convertedFromStoreFormat->format($this::STOREFORMAT);
        } else {
            $out = null;
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    /**
     * {@inheritDoc}
     */
    public function generateMapping(FieldType $current): array
    {
        return [
                $current->getName() => \array_merge([
                        'type' => 'date',
                        'format' => TimeFieldType::INDEXFORMAT,
                ], \array_filter($current->getMappingOptions())),
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('prefixIcon', static::getIcon());
        $resolver->setDefault('minuteStep', 15);
        $resolver->setDefault('showMeridian', false);
        $resolver->setDefault('defaultTime', 'current');
        $resolver->setDefault('showSeconds', false);
        $resolver->setDefault('explicitMode', true);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }

        $attr['class'] .= ' timepicker';
        $attr['data-show-meridian'] = $options['showMeridian'] ? 'true' : 'false';
//         $attr['data-provide'] = 'timepicker';
        $attr['data-default-time'] = $options['defaultTime'];
        $attr['data-show-seconds'] = $options['showSeconds'];
        $attr['data-explicit-mode'] = $options['explicitMode'];

        if ($options['minuteStep']) {
            $attr['data-minute-step'] = $options['minuteStep'];
        }

        $view->vars['attr'] = $attr;
    }

    public function getParent(): string
    {
        return IconTextType::class;
    }

    /**
     * {@inheritDoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('format', TextType::class, [
                'required' => false,
                'empty_data' => 'HH:mm:ss',
                'attr' => [
                    'placeholder' => 'i.e. HH:mm:ss',
                ],
            ]);
        }

        $optionsForm->get('displayOptions')->add('minuteStep', IntegerType::class, [
                'required' => false,
                'empty_data' => 15,
        ]);
        $optionsForm->get('displayOptions')->add('showMeridian', CheckboxType::class, [
                'required' => false,
                'label' => 'Show meridian (true: 12hr, false: 24hr)',
        ]);
        $optionsForm->get('displayOptions')->add('defaultTime', TextType::class, [
                'required' => false,
                 'label' => 'Default time (empty: current time, \'11:23\': specific time, \'false\': do not set a default time)',
        ]);
        $optionsForm->get('displayOptions')->add('showSeconds', CheckboxType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('explicitMode', CheckboxType::class, [
                'required' => false,
        ]);
    }
}
