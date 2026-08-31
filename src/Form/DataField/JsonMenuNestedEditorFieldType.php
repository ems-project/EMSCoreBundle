<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JsonMenuNestedEditorFieldType extends DataFieldType
{
    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return [
            'type' => 'array',
            'items' => ['$ref' => '#/$defs/jsonMenuNestedNode'],
            '$defs' => [
                'jsonMenuNestedNode' => $this->buildJsonMenuNestedNodeSchema($fieldType, $buildObjectSchema),
            ],
        ];
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'JSON menu nested editor field';
    }

    #[\Override]
    public function getParent(): string
    {
        return HiddenType::class;
    }

    #[\Override]
    public static function isContainer(): bool
    {
        return true;
    }

    #[\Override]
    public static function hasMappedChildren(): bool
    {
        return false;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'json_menu_nested_editor_fieldtype';
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'icon' => null,
                'blocks_template' => null,
                'json_menu_nested_modal' => true,
            ]);
    }

    #[\Override]
    public function generateMapping(FieldType $current): array
    {
        return [$current->getName() => ['type' => 'text']];
    }

    /**
     * @param callable(FieldType, mixed): mixed $buildChildValue
     */
    #[\Override]
    public function buildMcpRawDataValue(FieldType $fieldType, mixed $rawData, callable $buildChildValue): mixed
    {
        if (!\is_string($rawData) && !\is_array($rawData)) {
            return $rawData;
        }

        $jsonMenuNested = JsonMenuNested::fromStructure($rawData);
        $nestedTypes = [];
        foreach ($fieldType->getChildren() as $nestedContainer) {
            $nestedTypes[$nestedContainer->getName()] = $nestedContainer;
        }

        foreach ($jsonMenuNested as $item) {
            $nestedType = $nestedTypes[$item->getType()] ?? null;
            if (!$nestedType instanceof FieldType) {
                continue;
            }

            $itemObject = $item->getObject();
            $itemObject = $buildChildValue($nestedType, $itemObject);
            if (!\is_array($itemObject)) {
                continue;
            }

            $item->setObject($itemObject);
            if (\is_string($itemObject['label'] ?? null)) {
                $item->setLabel($itemObject['label']);
            }
        }

        return $jsonMenuNested->toArrayStructure();
    }

    #[\Override]
    public function mcpInputToRawValue(FieldType $fieldType, mixed $rawData): mixed
    {
        return \is_array($rawData) ? Json::encode($rawData) : $rawData;
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);

        $optionsForm = $builder->get('options');

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->get('mappingOptions')->add('analyzer', AnalyzerPickerType::class);
        }

        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'required' => false,
        ])->add('blocks_template', IconTextType::class, [
            'required' => false,
            'icon' => 'fa fa-html5',
        ]);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        /** @var Revision $revision */
        $revision = $form->getRoot()->getData();
        /** @var FieldType $fieldType */
        $fieldType = $options['metadata'];

        $view->vars['disabled'] = !$this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        $view->vars['revision'] = $revision;
    }

    /**
     * @param callable(array<FieldType>): array<string, mixed> $buildObjectSchema
     *
     * @return array<string, mixed>
     */
    private function buildJsonMenuNestedNodeSchema(FieldType $fieldType, callable $buildObjectSchema): array
    {
        $variants = [];
        foreach ($fieldType->getValidChildren() as $childFieldType) {
            $variants[] = $this->buildJsonMenuNestedVariantSchema($childFieldType, $buildObjectSchema);
        }

        if ([] === $variants) {
            return $this->buildJsonMenuNestedBaseSchema();
        }

        return ['anyOf' => $variants];
    }

    /**
     * @param callable(array<FieldType>): array<string, mixed> $buildObjectSchema
     *
     * @return array<string, mixed>
     */
    private function buildJsonMenuNestedVariantSchema(FieldType $childFieldType, callable $buildObjectSchema): array
    {
        $baseSchema = $this->buildJsonMenuNestedBaseSchema();

        return [
            'type' => 'object',
            'properties' => [
                ...$baseSchema['properties'],
                'type' => ['const' => $childFieldType->getName()],
                'object' => $buildObjectSchema($childFieldType->getValidChildren()),
            ],
            'required' => ['id', 'label', 'type', 'object'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonMenuNestedBaseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'children' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/jsonMenuNestedNode'],
                ],
            ],
            'required' => ['id', 'label'],
            'additionalProperties' => false,
        ];
    }
}
