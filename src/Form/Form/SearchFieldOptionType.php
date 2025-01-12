<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class SearchFieldOptionType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
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
                'Prefix' => 'prefix',
                'Match phrase' => 'match_phrase',
                'Match phrase prefix' => 'match_phrase_prefix',
            ],
        ])->add('contentTypes', ContentTypePickerType::class, [
            'multiple' => true,
            'required' => false,
        ])->add('save', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);

        if (!$options['createform']) {
            $builder->add('remove', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-trash',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'createform' => false,
        ]);
    }
}
