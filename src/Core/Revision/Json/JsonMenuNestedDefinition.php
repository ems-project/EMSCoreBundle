<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Json;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JsonMenuNestedDefinition
{
    private UrlGeneratorInterface $urlGenerator;
    private FieldType $fieldType;
    private JsonMenuNested $menu;
    private ?Revision $revision;

    public string $type;
    /** @var array<mixed> */
    private array $nodes;

    public bool $itemMove;
    public bool $itemCopy;
    public bool $itemPaste;
    public bool $itemAdd;
    public bool $itemEdit;
    public bool $itemDelete;
    public bool $itemPreview;

    public ?string $hiddenFieldId = null;

    /**
     * @param array<mixed> $options
     */
    public function __construct(UrlGeneratorInterface $urlGenerator, array $options = [])
    {
        $this->urlGenerator = $urlGenerator;
        $this->fieldType = $options['field_type'];
        $this->menu = JsonMenuNested::fromStructure($options['structure']);

        $this->type = $options['type'];
        $this->revision = $options['revision'] ?? null;
        $this->hiddenFieldId = $options['hidden_field_id'];

        $this->itemMove = $options['item_move'];
        $this->itemCopy = $options['item_copy'];
        $this->itemPaste = $options['item_paste'];
        $this->itemAdd = $options['item_add'];
        $this->itemEdit = $options['item_edit'];
        $this->itemDelete = $options['item_delete'];
        $this->itemPreview = $options['item_preview'];

        $this->nodes = $this->buildNodes();
    }

    /**
     * @return array{paste?: string, preview?: string}
     */
    public function getUrls(): array
    {
        $urls = [];

        if ($this->itemPaste && null !== $this->revision) {
            $urls['paste'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_paste', [
                'revision' => $this->revision->getId(),
                'fieldType' => $this->fieldType->getId(),
            ]);
        }
        if ($this->itemPreview) {
            $urls['preview'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_preview', [
                'parentFieldType' => $this->fieldType->getId(),
            ]);
        }

        return $urls;
    }

    public function getMaxDepth(): int
    {
        return $this->fieldType->getRestrictionOption('json_nested_max_depth', 0);
    }

    public function getMenu(): JsonMenuNested
    {
        return $this->menu;
    }

    /**
     * @return array<mixed>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array<mixed>
     */
    public function getNodeByName(string $name): array
    {
        foreach ($this->nodes as $node) {
            if ($node['name'] === $name) {
                return $node;
            }
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    private function buildNodes(): array
    {
        $nodes = [
            'root' => [
                'id' => 'root',
                'name' => 'root',
                'minimumRole' => $this->fieldType->getRestrictionOption('minimum_role', null),
                'deny' => $this->fieldType->getRestrictionOption('json_nested_deny', []),
                'isLeaf' => false,
            ],
        ];

        foreach ($this->fieldType->loopChildren() as $child) {
            if ($child->getDeleted() || !$child->getType()::isContainer()) {
                continue;
            }

            $node = [
                'id' => $child->getId(),
                'name' => $child->getName(),
                'minimumRole' => $child->getRestrictionOption('minimum_role', null),
                'label' => $child->getDisplayOption('label', $child->getName()),
                'icon' => $child->getDisplayOption('icon', null),
                'deny' => \array_merge(['root'], $child->getRestrictionOption('json_nested_deny', [])),
                'isLeaf' => $child->getRestrictionOption('json_nested_is_leaf', false),
            ];

            if ($this->itemAdd && null !== $this->revision) {
                $node['urlAdd'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_add', [
                    'revision' => $this->revision->getId(),
                    'fieldType' => $node['id'],
                ]);
            }
            if ($this->itemEdit && null !== $this->revision) {
                $node['urlEdit'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_edit', [
                    'revision' => $this->revision->getId(),
                    'fieldType' => $node['id'],
                ]);
            }

            $nodes[$child->getId()] = $node;
        }

        return $nodes;
    }
}
