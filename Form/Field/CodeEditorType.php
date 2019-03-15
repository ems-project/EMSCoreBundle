<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

class CodeEditorType extends AbstractType
{


    
    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return HiddenType::class;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                'min-lines' => 15,
                'max-lines' => 15,
                'language' => 'ace/mode/twig',
                'slug' => false
        ]);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars ['min_lines'] = $options ['min-lines'];
        $view->vars ['max_lines'] = $options ['max-lines'];
        $view->vars ['language'] = $options ['language'];
        $view->vars ['slug'] = $options ['slug'];
    }
}
