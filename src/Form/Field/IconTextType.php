<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconTextType extends TextType
{
    #[\Override]
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
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['icon'] = $options['icon'];
        $view->vars['class'] = $options['class'];
        $view->vars['prefixIcon'] = $options['prefixIcon'];
        $view->vars['prefixText'] = $options['prefixText'];
        $view->vars['suffixIcon'] = $options['suffixIcon'];
        $view->vars['suffixText'] = $options['suffixText'];
    }

    #[\Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'icontext';
    }
}
