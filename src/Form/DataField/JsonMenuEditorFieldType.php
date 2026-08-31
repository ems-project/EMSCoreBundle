<?php

declare(strict_types=1);

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
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return $this->buildLegacyJsonMenuSchema($fieldType, $buildObjectSchema);
    }

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

    /**
     * @param callable(array<FieldType>): array<string, mixed> $buildObjectSchema
     *
     * @return array<string, mixed>
     */
    protected function buildLegacyJsonMenuSchema(FieldType $fieldType, callable $buildObjectSchema): array
    {
        return [
            'type' => 'array',
            'items' => ['$ref' => '#/$defs/jsonMenuNode'],
            '$defs' => [
                'jsonMenuNode' => $this->buildLegacyJsonMenuNodeSchema($fieldType, $buildObjectSchema),
            ],
        ];
    }

    /**
     * @param callable(array<FieldType>): array<string, mixed> $buildObjectSchema
     *
     * @return array<string, mixed>
     */
    protected function buildLegacyJsonMenuNodeSchema(FieldType $fieldType, callable $buildObjectSchema): array
    {
        $variants = [];
        foreach ($fieldType->getValidChildren() as $childFieldType) {
            $variants[] = $this->buildLegacyJsonMenuVariantSchema($childFieldType, $buildObjectSchema, 'type');
            $variants[] = $this->buildLegacyJsonMenuVariantSchema($childFieldType, $buildObjectSchema, 'contentType');
        }

        if ([] === $variants) {
            return $this->buildLegacyJsonMenuBaseSchema();
        }

        return ['anyOf' => $variants];
    }

    /**
     * @param callable(array<FieldType>): array<string, mixed> $buildObjectSchema
     *
     * @return array<string, mixed>
     */
    protected function buildLegacyJsonMenuVariantSchema(FieldType $childFieldType, callable $buildObjectSchema, string $discriminatorProperty): array
    {
        $componentSchema = $buildObjectSchema($childFieldType->getValidChildren());
        $baseSchema = $this->buildLegacyJsonMenuBaseSchema();
        $componentProperties = \is_array($componentSchema['properties'] ?? null) ? $componentSchema['properties'] : [];
        $componentRequired = \is_array($componentSchema['required'] ?? null) ? $componentSchema['required'] : [];

        $properties = [
            ...$componentProperties,
            ...$baseSchema['properties'],
            $discriminatorProperty => ['const' => $childFieldType->getName()],
        ];

        if ('type' === $discriminatorProperty) {
            $properties['contentType'] = ['type' => 'string'];
        } else {
            $properties['type'] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => \array_values(\array_unique([...$componentRequired, 'id', 'label', $discriminatorProperty])),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLegacyJsonMenuBaseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'children' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/jsonMenuNode'],
                ],
                'contains' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['id', 'label'],
            'additionalProperties' => false,
        ];
    }
}
