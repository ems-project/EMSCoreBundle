<?php

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\SearchFieldOption;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_super'] || empty($options['searchFields'])) {
            $builder->add('field', TextType::class, [
                'required' => false,
            ]);
        } else {
            $builder->add('field', ChoiceType::class, [
                'choices' => $options['searchFieldsData'],
                'required' => false,
                'choice_attr' => function ($category, $key, $index) use ($options) {
                    /** @var SearchFieldOption $searchFieldOption */
                    $searchFieldOption = $options['searchFields'][$key];

                    return [
                        'data-content-types' => Json::encode($searchFieldOption->getContentTypes()),
                        'data-operators' => Json::encode($searchFieldOption->getOperators()),
                    ];
                },
            ]);
        }

        $builder->add('boost', $options['is_super'] ? NumberType::class : HiddenType::class, [
            'required' => false,
        ]);

        $builder->add('operator', ChoiceType::class, [
            'choices' => [
                'Query (and)' => 'query_and',
                'Query (or)' => 'query_or',
                'Match (and)' => 'match_and',
                'Match (or)' => 'match_or',
                'Term' => 'term',
                'Prefix' => 'prefix',
                'Match phrase' => 'match_phrase',
                'Match phrase prefix' => 'match_phrase_prefix',
                'Greater than' => 'gt',
                'Greater than or equal to' => 'gte',
                'Less than' => 'lt',
                'Less than or equal to' => 'lte',
            ],
        ]);

        $builder->add('booleanClause', ChoiceType::class, [
            'choices' => [
                'Must' => 'must',
                'Should' => 'should',
                'Must not' => 'must_not',
                'Filter' => 'filter',
            ],
        ]);

        $builder->add('pattern', TextType::class, [
            'required' => false,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchFilter::class,
            'is_super' => false,
            'searchFields' => [],
            'searchFieldsData' => [],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'search_filter';
    }
}
