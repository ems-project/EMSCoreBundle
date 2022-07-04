<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CodeEditorType extends AbstractType
{
    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
                'min-lines' => 15,
                'max-lines' => 15,
                'language' => 'ace/mode/twig',
                'slug' => false,
        ]);
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['min_lines'] = $options['min-lines'];
        $view->vars['max_lines'] = $options['max-lines'];
        $view->vars['language'] = $options['language'];
        $view->vars['slug'] = $options['slug'];
    }
}
