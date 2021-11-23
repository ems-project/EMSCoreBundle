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
    public array $nodes;
    /** @var string[] */
    public array $itemActions;

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
        $this->itemActions = $options['item_actions'] ?? [];

        $this->nodes = $this->buildNodes();
    }

    public function hasAction(string $action): bool
    {
        return \in_array($action, $this->itemActions, true);
    }

    /**
     * @return array{paste?: string, preview?: string}
     */
    public function getUrls(): array
    {
        $urls = [];

        if ($this->hasAction('paste') && null !== $this->revision) {
            $urls['paste'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_paste', [
                'revision' => $this->revision->getId(),
                'fieldType' => $this->fieldType->getId(),
            ]);
        }
        if ($this->hasAction('preview')) {
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
                'minimumRole' => $this->fieldType->getRestrictionOption('minimum_role'),
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
                'minimumRole' => $child->getRestrictionOption('minimum_role'),
                'label' => $child->getDisplayOption('label', $child->getName()),
                'icon' => $child->getDisplayOption('icon'),
                'deny' => \array_merge(['root'], $child->getRestrictionOption('json_nested_deny', [])),
                'isLeaf' => $child->getRestrictionOption('json_nested_is_leaf', false),
            ];

            if ($this->hasAction('add') && null !== $this->revision) {
                $node['urlAdd'] = $this->urlGenerator->generate('emsco_data_json_menu_nested_modal_add', [
                    'revision' => $this->revision->getId(),
                    'fieldType' => $node['id'],
                ]);
            }
            if ($this->hasAction('edit') && null !== $this->revision) {
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
