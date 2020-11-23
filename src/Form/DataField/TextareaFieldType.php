<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TextareaFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Textarea field';
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-edit';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var FieldType $fieldType */
        $fieldType = $builder->getOptions()['metadata'];
        $builder->add('value', TextareaType::class, [
                'attr' => [
                        'rows' => $options['rows'],
                        'placeholder' => $options['placeholder'],
                ],
                'label' => (null != $options['label'] ? $options['label'] : $fieldType->getName()),
                'required' => false,
                'disabled' => $this->isDisabled($options),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /*get options for twig context*/
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /*set the default option value for this kind of compound field*/
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('rows', null);
        $resolver->setDefault('placeholder', null);
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific mapping options
        $optionsForm->get('mappingOptions')
        ->add('analyzer', AnalyzerPickerType::class)
        ->add('copy_to', TextType::class, [
                'required' => false,
        ]);
        $optionsForm->get('displayOptions')->add('rows', IntegerType::class, [
                'required' => false,
        ])->add('placeholder', TextareaType::class, [
            'required' => false,
        ]);
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
