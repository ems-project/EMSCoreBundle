<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonMenuEditorFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'JSON menu editor field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-sitemap';
    }

    #[\Override]
    public function getParent(): string
    {
        return HiddenType::class;
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $disabled = true;
        if ($options['metadata'] instanceof FieldType) {
            $disabled = !$this->authorizationChecker->isGranted($options['metadata']->getMinimumRole());
        }

        $attr = \array_merge(
            [
                'class' => '',
            ],
            $view->vars['attr'],
            [
                'data-disabled' => $disabled,
                'data-locales' => $options['locales'],
                'data-max-depth' => $options['maxDepth'],
            ]
        );
        $attr['class'] .= ' code_editor_ems';

        $view->vars['attr'] = $attr;
        $view->vars['icon'] = $options['icon'];
        $view->vars['item_types'] = $options['itemTypes'];
        $view->vars['node_types'] = $options['nodeTypes'];
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'json_menu_editor_fieldtype';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('icon', null);
        $resolver->setDefault('locales', null);
        $resolver->setDefault('maxDepth', 15);
        $resolver->setDefault('itemTypes', 'item');
        $resolver->setDefault('nodeTypes', 'node');
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }

        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false,
        ])->add('maxDepth', IntegerType::class, [
            'required' => false,
        ])->add('nodeTypes', TextType::class, [
            'required' => false,
        ])->add('itemTypes', TextType::class, [
            'required' => false,
        ]);
    }
}
