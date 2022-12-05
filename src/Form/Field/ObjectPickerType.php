<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectPickerType extends Select2Type
{
    public function __construct(private readonly ObjectChoiceListFactory $choiceListFactory)
    {
        parent::__construct($choiceListFactory);
    }

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
                    return ['title' => $val->getTitle()];
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
            'referrer-ems-id' => null,
        ]);
    }

    public function getChoiceListFactory(): ObjectChoiceListFactory
    {
        return $this->choiceListFactory;
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-type'] = $options['type'];
        $view->vars['attr']['data-search-id'] = $options['searchId'];
        $view->vars['attr']['data-circle-only'] = $options['circle-only'];
        $view->vars['attr']['data-dynamic-loading'] = $options['dynamicLoading'];
        $view->vars['attr']['data-sortable'] = $options['sortable'];
        $view->vars['attr']['data-query-search'] = $options['querySearch'];
        $view->vars['attr']['data-referrer-ems-id'] = $options['referrer-ems-id'] ?? false;
    }

    public function getBlockPrefix(): string
    {
        return 'objectpicker';
    }
}
