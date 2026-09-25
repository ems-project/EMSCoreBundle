<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

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
            'label' => t('field.name', [], 'emsco-core'),
        ])
        ->add('field', TextType::class, [
            'label' => t('field.field', [], 'emsco-core'),
        ])
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ])->add('operators', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'label' => t('field.operators', [], 'emsco-core'),
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
            'label' => t('field.content_types', [], 'emsco-core'),
            'multiple' => true,
            'required' => false,
        ]);
    }
}
