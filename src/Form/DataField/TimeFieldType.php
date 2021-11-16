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
    public const STOREFORMAT = 'H:i:s';
    public const INDEXFORMAT = 'HH:mm:ss';

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Time field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'fa fa-clock-o';
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

            $timeObject = \DateTime::createFromFormat($format, \strval($sourceArray));
            if ($timeObject) {
                $dataField->setRawData($timeObject->format(\DateTime::ISO8601));
            } else {
                $dataField->addMessage('Not able to parse the date');
            }
        }

        return [$dataField->getFieldType()->getName()];
    }

    /**
     * Convert options into PHP date format string.
     *
     * @param array $options
     *
     * @return string
     */
    public static function getFormat($options)
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
     * {@inheritdoc}
     */
    public function viewTransform(DataField $data)
    {
        $out = parent::viewTransform($data);

        if (\is_array($out) && 0 === \count($out)) {
            return ''; //empty array means null/empty
        }

        $format = $this->getFormat($data->getFieldType()->getOptions());

        /** @var \DateTime $converted */
        $dateTime = \DateTime::createFromFormat(TimeFieldType::STOREFORMAT, \strval($out));
        if ($dateTime) {
            return $dateTime->format($format);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function reverseViewTransform($data, FieldType $fieldType)
    {
        $format = $this->getFormat($fieldType->getOptions());
        $converted = \DateTime::createFromFormat($format, \strval($data));
        if ($converted) {
            $out = $converted->format($this::STOREFORMAT);
        } else {
            $out = null;
        }

        return parent::reverseViewTransform($out, $fieldType);
    }

    /**
     * {@inheritdoc}
     */
    public function generateMapping(FieldType $current)
    {
        return [
                $current->getName() => \array_merge([
                        'type' => 'date',
                        'format' => TimeFieldType::INDEXFORMAT,
                ], \array_filter($current->getMappingOptions())),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('prefixIcon', $this->getIcon());
        $resolver->setDefault('minuteStep', 15);
        $resolver->setDefault('showMeridian', false);
        $resolver->setDefault('defaultTime', 'current');
        $resolver->setDefault('showSeconds', false);
        $resolver->setDefault('explicitMode', true);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /*get options for twig context*/
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

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return IconTextType::class;
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
