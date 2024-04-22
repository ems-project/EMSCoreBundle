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

class CodeFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Code editor field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-code';
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $fieldType = $options['metadata'];

        /* get options for twig context */
        parent::buildView($view, $form, $options);
        $view->vars['icon'] = $options['icon'];

        $attr = $view->vars['attr'];
        if (empty($attr['class'])) {
            $attr['class'] = '';
        }

        $attr['data-max-lines'] = $options['maxLines'];
        $attr['data-language'] = $options['language'];
        $attr['data-height'] = $options['height'];
        $attr['data-theme'] = $options['theme'];
        $attr['data-disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        $attr['class'] .= ' code_editor_ems';

        $view->vars['attr'] = $attr;
    }

    public function getBlockPrefix(): string
    {
        return 'codefieldtype';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('language', null);
        $resolver->setDefault('theme', null);
        $resolver->setDefault('maxLines', 15);
        $resolver->setDefault('height', false);
        $resolver->setDefault('required', false);
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false,
        ])->add('maxLines', IntegerType::class, [
            'required' => false,
        ])->add('height', IntegerType::class, [
            'required' => false,
        ])->add('language', TextType::class, [
            'required' => false,
            'attr' => [
                'class' => 'code_editor_mode_ems',
            ],
        ])->add('theme', TextType::class, [
            'required' => false,
            'attr' => [
                'class' => 'code_editor_theme_ems',
            ],
        ]);
    }
}
