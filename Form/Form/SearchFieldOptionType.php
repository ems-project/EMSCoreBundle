<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use EMS\CoreBundle\Form\Field\IconPickerType;

class SearchFieldOptionType extends AbstractType
{
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        
        $builder
        ->add('name', IconTextType::class, [
                'icon' => 'fa fa-tag',
                'label' => 'Search Field Option\'s name',
        ])
        ->add('field', TextType::class, [
            'label' => 'Search Field',
        ])
        ->add('icon', IconPickerType::class, [
                'required' => false,
        ])->add('operators', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => [
                'Query (and)' => 'query_and',
                'Query (or)' => 'query_or',
                'Match (and)' => 'match_and',
                'Match (or)' => 'match_or',
                'Term' => 'term',
            ]
        ])->add('contentTypes', ContentTypePickerType::class, [
            'multiple' => true,
            'required' => false,
        ])->add('save', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save'
        ]);
        
        if (! $options['createform']) {
            $builder->add('remove', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm '
                    ],
                    'icon' => 'fa fa-trash'
            ]);
        }
    }
    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array (
                'createform' => false,
        ));
    }
}
