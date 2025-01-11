<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\FieldType;

use Doctrine\Common\Collections\ArrayCollection;
use EMS\CoreBundle\Entity\FieldType;

/**
 * @implements \IteratorAggregate<FieldTypeTreeItem>
 */
class FieldTypeTreeItem implements \IteratorAggregate, \Stringable
{
    private readonly FieldTypeTreeItemCollection $children;
    private ?FieldTypeTreeItem $parent = null;
    private readonly string $name;

    /**
     * @param ArrayCollection<int, FieldType> $fieldTypes
     */
    public function __construct(
        private readonly FieldType $fieldType,
        ArrayCollection $fieldTypes,
    ) {
        $this->name = $this->fieldType->getName();
        $children = [];
        $childFieldTypes = $fieldTypes->filter(fn (FieldType $f) => $f->getParent()?->getId() === $fieldType->getId());

        foreach ($childFieldTypes as $childFieldType) {
            $child = new FieldTypeTreeItem($childFieldType, $fieldTypes);
            $child->setParent($this);
            $children[$child->fieldType->getOrderKey()] = $child;
        }

        \ksort($children);
        $this->children = new FieldTypeTreeItemCollection($children);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name;
    }

    public function addChild(FieldTypeTreeItem $child): self
    {
        $this->children->set($child->fieldType->getOrderKey(), $child);

        return $this;
    }

    public function getChildren(): FieldTypeTreeItemCollection
    {
        return $this->children;
    }

    public function getChildrenRecursive(): FieldTypeTreeItemCollection
    {
        return new FieldTypeTreeItemCollection($this->toArray());
    }

    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }

    /**
     * @return \Traversable<FieldTypeTreeItem>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \RecursiveArrayIterator($this->toArray());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayOptionsLabel(): ?string
    {
        $fieldType = $this->getFieldType();
        $options = $fieldType->getOptions();

        return $options['displayOptions']['label'] ?? null;
    }

    /**
     * @return FieldTypeTreeItem[]
     */
    public function getPath(): array
    {
        $path = [$this];

        if ($this->parent) {
            $path = [...$this->parent->getPath(), ...$path];
        }

        return $path;
    }

    /**
     * @return FieldTypeTreeItem[]
     */
    public function toArray(): array
    {
        $data = [$this];

        foreach ($this->children as $child) {
            $data = [...$data, ...$child->toArray()];
        }

        return $data;
    }

    public function setParent(?FieldTypeTreeItem $parent): void
    {
        $this->parent = $parent;
    }

    public function getParent(): ?FieldTypeTreeItem
    {
        return $this->parent;
    }
}
