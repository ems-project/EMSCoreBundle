<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconTextType extends TextType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'metadata' => null,
            'icon' => null,
            'class' => null,
        ]);
        $resolver->setDefault('prefixIcon', null);
        $resolver->setDefault('prefixText', null);
        $resolver->setDefault('suffixIcon', null);
        $resolver->setDefault('suffixText', null);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['icon'] = $options['icon'];
        $view->vars['class'] = $options['class'];
        $view->vars['prefixIcon'] = $options['prefixIcon'];
        $view->vars['prefixText'] = $options['prefixText'];
        $view->vars['suffixIcon'] = $options['suffixIcon'];
        $view->vars['suffixText'] = $options['suffixText'];
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'icontext';
    }
}
