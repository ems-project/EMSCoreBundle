<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonMenuEditorFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'JSON menu editor field';
    }

    /**
     * {@inheritdoc}
     */
    public static function getIcon()
    {
        return 'fa fa-sitemap';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return HiddenType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $fieldType = $options['metadata'];

        /*get options for twig context*/
        parent::buildView($view, $form, $options);
        $view->vars ['icon'] = $options ['icon'];

        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }

        $attr['data-locales'] = $options['locales'];
        $attr['data-language'] = $options['language'];
        $attr['data-maxDepth'] = $options['maxDepth'];
        $attr['data-theme'] = $options['theme'];
        $attr['data-disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        $attr['class'] .= ' code_editor_ems';

        $view->vars ['attr'] = $attr;
    }


    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'json_menu_editor_fieldtype';
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /*set the default option value for this kind of compound field*/
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('locales', null);
        $resolver->setDefault('maxDepth', 15);
        $resolver->setDefault('types', false);
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific mapping options
        $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false
        ])->add('maxDepth', IntegerType::class, [
            'required' => false,
        ])->add('locales', TextType::class, [
            'required' => false,
        ])->add('types', TextType::class, [
            'required' => false,
        ]);
    }
}
