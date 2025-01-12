<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Service\QuerySearchService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectPickerType extends Select2Type
{
    public function __construct(
        private readonly ObjectChoiceListFactory $choiceListFactory,
        private readonly QuerySearchService $querySearchService,
    ) {
        parent::__construct($choiceListFactory);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'required' => false,
            'dynamicLoading' => true,
            'sortable' => false,
            'with_warning' => true,
            'choice_loader' => function (Options $options) {
                $loadAll = !$options['dynamicLoading'];
                $circleOnly = $options['circle-only'];
                $withWarning = $options['with_warning'];
                $querySearch = $options['querySearch'];
                if (!\is_string($querySearch) || 0 === \strlen($querySearch)) {
                    $querySearch = null;
                }

                return $this->choiceListFactory->createLoader($options['type'], $loadAll, $circleOnly, $withWarning, $querySearch);
            },
            'choice_label' => fn ($value, $key, $index) => $value->getLabel(),
            'choice_attr' => function ($val, $key, $index) {
                if ($val instanceof ObjectChoiceListItem) {
                    return \array_filter([
                        'title' => $val->getTitle(),
                        'data-tooltip' => $val->getTooltip(),
                    ]);
                }

                return [];
            },
            'group_by' => fn ($value, $key, $index) => $value->getGroup(),
            'choice_value' => fn ($value) => $value->getValue(),
            'multiple' => false,
            'type' => null,
            'searchId' => null,
            'circle-only' => false,
            'querySearch' => null,
            'querySearchLabel' => null,
            'referrer-ems-id' => null,
        ]);
    }

    public function getChoiceListFactory(): ObjectChoiceListFactory
    {
        return $this->choiceListFactory;
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $querySearch = $options['querySearch'];
        $querySearchLabel = $this->getQuerySearchLabel($querySearch);
        $view->vars['attr']['data-type'] = $options['type'];
        $view->vars['attr']['data-search-id'] = $options['searchId'];
        $view->vars['attr']['data-circle-only'] = $options['circle-only'];
        $view->vars['attr']['data-dynamic-loading'] = $options['dynamicLoading'];
        $view->vars['attr']['data-sortable'] = $options['sortable'];
        $view->vars['attr']['data-query-search'] = $querySearch;
        $view->vars['attr']['data-query-search-label'] = $querySearchLabel;
        $view->vars['attr']['data-referrer-ems-id'] = $options['referrer-ems-id'] ?? false;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'objectpicker';
    }

    private function getQuerySearchLabel(?string $querySearch): ?string
    {
        if (null === $querySearch) {
            return null;
        }
        try {
            $querySearchEntity = $this->querySearchService->getByItemName($querySearch);
            if ($querySearchEntity instanceof QuerySearch) {
                return $querySearchEntity->getLabel();
            }
        } catch (\RuntimeException) {
        }

        return null;
    }
}
