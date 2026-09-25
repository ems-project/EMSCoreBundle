<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Subform\SearchFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class SearchFormType extends AbstractType
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isSuper = $this->authorizationChecker->isGranted('ROLE_SUPER');

        $searchFields = [];
        $searchFieldsData = [];
        if ($options['dashboardOptions'] instanceof DashboardOptions && [] !== $options['dashboardOptions']->getArray(DashboardOptions::SEARCH_FIELD_OPTIONS)) {
            foreach ($options['dashboardOptions']->getArray(DashboardOptions::SEARCH_FIELD_OPTIONS) as $searchFieldOption) {
                $searchFieldsData[$searchFieldOption['name']] = $searchFieldOption['field'];
                $searchFields[$searchFieldOption['name']] = $searchFieldOption;
            }
        }

        $builder->add('filters', CollectionType::class, [
            'label' => t('field.filters', [], 'emsco-core'),
            'entry_type' => SearchFilterType::class,
            'allow_add' => true,
            'entry_options' => [
                'is_super' => $isSuper,
                'searchFieldsData' => $searchFieldsData,
                'searchFields' => $searchFields,
            ],
        ]);
        if ($options['light']) {
            $builder->add('applyFilters', SubmitEmsType::class, [
                'label' => t('action.apply_filters', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-md',
                    'data-testid' => 'btn-action-apply-filters',
                ],
                'icon' => 'fa fa-check',
            ]);
        } else {
            if ($options['dashboardOptions'] instanceof DashboardOptions && [] !== $options['dashboardOptions']->getArray(DashboardOptions::SORT_OPTIONS)) {
                $sortFields = [];
                $sortFieldIcons = [];
                foreach ($options['dashboardOptions']->getArray(DashboardOptions::SORT_OPTIONS) as $sortOption) {
                    $sortFields[$sortOption['name']] = $sortOption['field'];
                    $sortFieldIcons[$sortOption['field']] = $sortOption['icon'];
                }

                $builder->add('sortBy', ChoiceType::class, [
                    'label' => t('field.sort_by', [], 'emsco-core'),
                    'required' => false,
                    'choices' => $sortFields,
                    'choice_label' => fn ($value, $label) => $label,
                    'choice_translation_domain' => false,
                    'choice_attr' => static fn (
                        mixed $choice,
                        string $label,
                        mixed $value,
                    ): array => [
                        'data-icon' => $sortFieldIcons[$value] ?? '',
                    ],
                    'attr' => [
                        'class' => 'select2',
                    ],
                ]);
            } else {
                $builder->add('sortBy', TextType::class, [
                    'label' => t('field.sort_by', [], 'emsco-core'),
                    'required' => false,
                ]);
            }

            $builder->add('sortOrder', ChoiceType::class, [
                'label' => t('field.sort_order', [], 'emsco-core'),
                'choices' => [
                    'key.ascending' => 'asc',
                    'key.descending' => 'desc',
                ],
                'choice_label' => fn ($value, $label) => t($label, [], 'emsco-core'),
                'choice_attr' => static fn (
                    mixed $choice,
                    string $label,
                    mixed $value,
                ): array => [
                    'data-icon' => match ($value) {
                        'asc' => 'fa fa-sort-asc',
                        'desc' => 'fa fa-sort-desc',
                        default => '',
                    },
                ],
                'attr' => [
                    'class' => 'select2',
                ],
                'required' => false,
            ]);

            $builder->add('minimumShouldMatch', IntegerType::class, [
                'label' => t('field.minimum_should_match', [], 'emsco-core'),
                'required' => false,
                'empty_data' => '1',
                'attr' => [
                    'min' => 0,
                ],
            ]);

            $builder->add('search', SubmitEmsType::class, [
                'label' => t('key.search', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-md',
                    'data-testid' => 'btn-action-search',
                ],
                'icon' => 'fa fa-search',
            ])->add('exportResults', SubmitEmsType::class, [
                'label' => t('action.export', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-testid' => 'btn-action-export',
                ],
                'icon' => 'fa fa-archive',
            ])->add('environments', EnvironmentPickerType::class, [
                'label' => t('field.environments', [], 'emsco-core'),
                'multiple' => true,
                'required' => false,
                'managedOnly' => false,
                'userPublishEnvironments' => false,
            ])->add('contentTypes', ContentTypePickerType::class, [
                'label' => t('field.content_types', [], 'emsco-core'),
                'multiple' => true,
                'required' => false,
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Search::class,
            'csrf_protection' => false,
            'light' => false,
            'dashboardOptions' => null,
        ]);
    }
}
