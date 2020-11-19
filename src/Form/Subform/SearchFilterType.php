<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\Entity\SearchFieldOption;
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
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
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
                        'data-content-types' => \json_encode($searchFieldOption->getContentTypes()),
                        'data-operators' => \json_encode($searchFieldOption->getOperators()),
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'EMS\CoreBundle\Entity\Form\SearchFilter',
            'is_super' => false,
            'searchFields' => [],
            'searchFieldsData' => [],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'search_filter';
    }
}
