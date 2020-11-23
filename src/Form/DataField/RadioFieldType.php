<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RadioFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Radio field';
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-dot-circle-o';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];

        $choices = [];
        $values = explode("\n", str_replace("\r", '', $options['choices']));
        $labels = explode("\n", str_replace("\r", '', $options['labels']));

        foreach ($values as $id => $value) {
            if (isset($labels[$id]) && !empty($labels[$id])) {
                $choices[$labels[$id]] = $value;
            } else {
                $choices[$value] = $value;
            }
        }

        $builder->add('value', ChoiceType::class, [
                'label' => (isset($options['label']) ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
                'choices' => $choices,
                'empty_data' => null,
                'multiple' => false,
                'expanded' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('choices', []);
        $resolver->setDefault('labels', []);
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('choices', TextareaType::class, [
                'required' => false,
        ])->add('labels', TextareaType::class, [
                'required' => false,
        ]);

        // String specific mapping options
        $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
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
        $value = $data['value'];

        return parent::reverseViewTransform($value, $fieldType);
    }
}
