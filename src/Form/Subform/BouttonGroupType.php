<?php

namespace EMS\CoreBundle\Form\Subform;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BouttonGroupType extends TextType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'buttons' => [],
        ]);
    }

    /**
     * @param FormView<FormView>           $view
     * @param FormInterface<FormInterface> $form
     * @param array<mixed>                 $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['buttons'] = $options['buttons'];
    }

    #[\Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'bouttongroup';
    }
}
