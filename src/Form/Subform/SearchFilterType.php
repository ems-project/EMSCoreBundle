<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Subform;

use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class SearchFilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_super'] || empty($options['searchFields'])) {
            $builder->add('field', TextType::class, [
                'label' => t('field.field', [], 'emsco-core'),
                'required' => false,
            ]);
        } else {
            $builder->add('field', ChoiceType::class, [
                'label' => t('field.field', [], 'emsco-core'),
                'choices' => $options['searchFieldsData'],
                'required' => false,
                'choice_translation_domain' => false,
                'choice_attr' => function ($category, $key, $index) use ($options) {
                    $searchFieldOption = $options['searchFields'][$key];

                    return [
                        'data-content-types' => Json::encode($searchFieldOption['contentTypes']),
                        'data-operators' => Json::encode($searchFieldOption['operators']),
                    ];
                },
            ]);
        }

        $builder->add('boost', $options['is_super'] ? NumberType::class : HiddenType::class, [
            'label' => t('field.boost', [], 'emsco-core'),
            'required' => false,
        ]);

        $builder->add('operator', ChoiceType::class, [
            'label' => t('field.operator', [], 'emsco-core'),
            'choices' => [
                t('key.query_and', [], 'emsco-core')->getMessage() => 'query_and',
                t('key.query_or', [], 'emsco-core')->getMessage() => 'query_or',
                t('key.match_and', [], 'emsco-core')->getMessage() => 'match_and',
                t('key.match_or', [], 'emsco-core')->getMessage() => 'match_or',
                t('key.term', [], 'emsco-core')->getMessage() => 'term',
                t('key.prefix', [], 'emsco-core')->getMessage() => 'prefix',
                t('key.match_phrase', [], 'emsco-core')->getMessage() => 'match_phrase',
                t('key.match_phrase_prefix', [], 'emsco-core')->getMessage() => 'match_phrase_prefix',
                t('key.gt', [], 'emsco-core')->getMessage() => 'gt',
                t('key.gte', [], 'emsco-core')->getMessage() => 'gte',
                t('key.lt', [], 'emsco-core')->getMessage() => 'lt',
                t('key.lte', [], 'emsco-core')->getMessage() => 'lte',
            ],
            'choice_translation_domain' => 'emsco-core',
        ]);

        $builder->add('booleanClause', ChoiceType::class, [
            'label' => t('field.boolean_clause', [], 'emsco-core'),
            'choices' => [
                t('key.must', [], 'emsco-core')->getMessage() => 'must',
                t('key.should', [], 'emsco-core')->getMessage() => 'should',
                t('key.must_not', [], 'emsco-core')->getMessage() => 'must_not',
                t('key.filter', [], 'emsco-core')->getMessage() => 'filter',
            ],
            'choice_translation_domain' => 'emsco-core',
        ]);

        $builder->add('pattern', TextType::class, [
            'label' => t('field.pattern', [], 'emsco-core'),
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
