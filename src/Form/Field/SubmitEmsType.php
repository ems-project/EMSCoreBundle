<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubmitEmsType extends SubmitType
{
    public function getParent(): string
    {
        return SubmitType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'submitems';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'icon' => null,
            'message' => null,
            'confirm' => null,
            'confirm_class' => null,
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['icon'] = $options['icon'];
        $view->vars['message'] = $options['message'];
        $view->vars['confirm'] = $options['confirm'];
        $view->vars['confirm_class'] = $options['confirm_class'];
    }
}
