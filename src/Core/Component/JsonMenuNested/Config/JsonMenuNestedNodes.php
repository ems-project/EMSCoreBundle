<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\FieldType;

class JsonMenuNestedNodes
{
    public string $path;
    public JsonMenuNestedNode $root;
    /** @var array<string, JsonMenuNestedNode> */
    private array $nodes = [];

    public function __construct(FieldType $fieldType)
    {
        $this->path = $fieldType->getPath();

        if (!$fieldType->isJsonMenuNestedEditorField()) {
            throw new \RuntimeException('invalid field');
        }

        $this->root = JsonMenuNestedNode::fromFieldType($fieldType);

        $children = $fieldType->getChildren()
            ->filter(fn (FieldType $child) => !$child->isDeleted() && $child->isContainer());

        foreach ($children as $child) {
            $node = JsonMenuNestedNode::fromFieldType($child);
            $this->nodes[$node->type] = $node;
        }
    }

    /**
     * @throws JsonMenuNestedConfigException
     */
    public function getById(int $nodeId): JsonMenuNestedNode
    {
        foreach ($this->nodes as $node) {
            if ($node->id === $nodeId) {
                return $node;
            }
        }

        throw JsonMenuNestedConfigException::nodeNotFound();
    }

    public function get(JsonMenuNested $item): JsonMenuNestedNode
    {
        if (!isset($this->nodes[$item->getType()])) {
            throw JsonMenuNestedConfigException::nodeNotFound();
        }

        return $this->nodes[$item->getType()];
    }

    public function getByType(string $type): JsonMenuNestedNode
    {
        if ('_root' === $type) {
            return $this->root;
        }

        foreach ($this->nodes as $node) {
            if ($node->type === $type) {
                return $node;
            }
        }

        throw JsonMenuNestedConfigException::nodeNotFound();
    }

    /**
     * @return string[]
     */
    public function getTypes(string $parentType): array
    {
        $parentNode = $this->getByType($parentType);

        return \array_keys($this->getChildren($parentNode));
    }

    /**
     * @return JsonMenuNestedNode[]
     */
    public function getChildren(JsonMenuNestedNode $parentNode): array
    {
        if ($parentNode->leaf) {
            return [];
        }

        return \array_filter(
            $this->nodes,
            static fn (JsonMenuNestedNode $node) => !\in_array($node->type, $parentNode->deny)
        );
    }
}
