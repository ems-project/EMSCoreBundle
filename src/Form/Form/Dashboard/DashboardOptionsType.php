<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\Dashboard\Services\AdvancedSearch;
use EMS\CoreBundle\Core\Dashboard\Services\DashboardInterface;
use EMS\CoreBundle\Core\Dashboard\Services\Export;
use EMS\CoreBundle\Core\Dashboard\Services\Template;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ContentTypePickerType;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Form\AggregateOptionType;
use EMS\CoreBundle\Form\Form\SearchFieldOptionType;
use EMS\CoreBundle\Form\Form\SortOptionType;
use EMS\CoreBundle\Form\Subform\SearchFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class DashboardOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dashboard = $options['dashboard'];

        if ($dashboard instanceof Export || $dashboard instanceof Template) {
            $builder->add(DashboardOptions::BODY, CodeEditorType::class, [
                'label' => t('field.template_body', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ]);
        }

        match ($dashboard::class) {
            Export::class => $this->buildForExport($builder) ,
            Template::class => $this->buildForTemplate($builder),
            AdvancedSearch::class => $this->buildForAdvancedSearch($builder),
            default => null,
        };
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function buildForTemplate(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::HEADER, CodeEditorType::class, [
                'label' => t('field.template_header', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FOOTER, CodeEditorType::class, [
                'label' => t('field.template_footer', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function buildForExport(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::FILENAME, CodeEditorType::class, [
                'label' => t('field.file.name', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add(DashboardOptions::MIMETYPE, null, [
                'label' => t('field.file.mimetype', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FILE_DISPOSITION, ChoiceType::class, [
                'label' => t('field.file.disposition', [], 'emsco-core'),
                'expanded' => true,
                'row_attr' => ['class' => 'col-md-12'],
                'choices' => [
                    t('key.none', [], 'emsco-core')->getMessage() => null,
                    t('key.attachment', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    t('key.inline', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_INLINE,
                ],
                'choice_translation_domain' => 'emsco-core',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'label' => false,
            ])
            ->setNormalizer(
                'label_format',
                fn (Options $options) => 'dashboard.'.\strtolower(new \ReflectionClass($options['dashboard'])->getShortName()).'.%name%'
            )
            ->setRequired(['dashboard'])
            ->setAllowedTypes('dashboard', DashboardInterface::class)
        ;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function buildForAdvancedSearch(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::ENVIRONMENTS, EnvironmentPickerType::class, [
                'label' => t('field.environments', [], 'emsco-core'),
                'required' => false,
                'multiple' => true,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::CONTENT_TYPES, ContentTypePickerType::class, [
                'label' => t('field.content_types', [], 'emsco-core'),
                'required' => false,
                'multiple' => true,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::SORT_BY, null, [
                'label' => t('field.sort_by', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::SORT_ORDER, ChoiceType::class, [
                'label' => t('field.sort_order', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'choices' => [
                    t('key.ascending', [], 'emsco-core')->getMessage() => 'asc',
                    t('key.descending', [], 'emsco-core')->getMessage() => 'desc',
                ],
            ])
            ->add(DashboardOptions::MINIMUM_SHOULD_MATCH, IntegerType::class, [
                'label' => t('field.minimum_should_match', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'empty_data' => 1,
            ])
            ->add(DashboardOptions::FILTERS, CollectionType::class, [
                'label' => t('field.filters', [], 'emsco-core'),
                'allow_add' => true,
                'allow_delete' => true,
                'entry_type' => SearchFilterType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => t('action.add_type', ['type' => 'filter'], 'emsco-core'),
                    'data-lang-remove' => t('action.remove_type', ['type' => 'filter'], 'emsco-core'),
                    'data-entry-remove-class' => 'btn btn-sm btn-danger',
                ],
                'entry_options' => [
                    'data_class' => null,
                ],
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::SORT_OPTIONS, CollectionType::class, [
                'label' => t('field.sort_options', [], 'emsco-core'),
                'allow_add' => true,
                'allow_delete' => true,
                'entry_type' => SortOptionType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => t('action.add_type', ['type' => 'sort_option'], 'emsco-core'),
                    'data-lang-remove' => t('action.remove_type', ['type' => 'sort_option'], 'emsco-core'),
                    'data-entry-remove-class' => 'btn btn-sm btn-danger',
                ],
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::AGGREGATE_OPTIONS, CollectionType::class, [
                'label' => t('field.aggregate_options', [], 'emsco-core'),
                'allow_add' => true,
                'allow_delete' => true,
                'entry_type' => AggregateOptionType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => t('action.add_type', ['type' => 'aggregate_option'], 'emsco-core'),
                    'data-lang-remove' => t('action.remove_type', ['type' => 'aggregate_option'], 'emsco-core'),
                    'data-entry-remove-class' => 'btn btn-sm btn-danger',
                ],
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::SEARCH_FIELD_OPTIONS, CollectionType::class, [
                'label' => t('field.search_field_options', [], 'emsco-core'),
                'allow_add' => true,
                'allow_delete' => true,
                'entry_type' => SearchFieldOptionType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => t('action.add_type', ['type' => 'search_field_option'], 'emsco-core'),
                    'data-lang-remove' => t('action.remove_type', ['type' => 'search_field_option'], 'emsco-core'),
                    'data-entry-remove-class' => 'btn btn-sm btn-danger',
                ],
                'row_attr' => ['class' => 'col-md-12'],
            ]);
    }
}
