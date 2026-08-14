<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldType extends JsonDeserializer implements \JsonSerializable
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    final public const string DISPLAY_OPTIONS = 'displayOptions';
    final public const string MAPPING_OPTIONS = 'mappingOptions';

    /** @var class-string<DataFieldType> */
    protected string $type;
    /** @var string */
    protected $name;
    protected ?ContentType $contentType = null;
    /** @var bool */
    protected $deleted = false;
    /** @var string */
    protected $description;
    /** @var array<mixed>|null */
    protected ?array $options = [];
    /** @var array<mixed> */
    protected array $displayExtraOptions = [];
    /** @var int */
    protected $orderKey = 0;
    protected ?FieldType $parent = null;
    /** @var Collection<int, FieldType> */
    protected Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();

        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    /**
     * Update contentType and parent recursively.
     */
    // TODO: Unrecursify this method
    public function updateAncestorReferences(?ContentType $contentType, ?FieldType $parent): void
    {
        $this->setContentType($contentType);
        $this->setParent($parent);
        foreach ($this->children as $child) {
            $child->updateAncestorReferences(null, $this);
        }
    }

    public function updateOrderKeys(): void
    {
        if (null != $this->children) {
            /** @var FieldType $child */
            foreach ($this->children as $key => $child) {
                $child->setOrderKey($key);
                $child->updateOrderKeys();
            }
        }
    }

    /**
     * Remove references to parent to prevent circular reference exception.
     */
    public function removeCircularReference(): void
    {
        if (null != $this->children) {
            /** @var FieldType $child */
            foreach ($this->children as $child) {
                $child->removeCircularReference();
            }
            $this->setContentType();
            $this->setParent();
        }
    }

    /**
     * @return array<mixed>
     */
    public function getFieldsRoles(): array
    {
        $out = ['ROLE_AUTHOR' => 'ROLE_AUTHOR'];
        if (isset($this->getOptions()['restrictionOptions']) && isset($this->getOptions()['restrictionOptions']['minimum_role']) && $this->getOptions()['restrictionOptions']['minimum_role']) {
            $out[$this->getOptions()['restrictionOptions']['minimum_role']] = $this->getOptions()['restrictionOptions']['minimum_role'];
        }

        foreach ($this->children as $child) {
            $out = \array_merge($out, $child->getFieldsRoles());
        }

        return $out;
    }

    /**
     * @param class-string<DataFieldType> $type
     */
    public function setType(string $type): FieldType
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return class-string<DataFieldType>
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return FieldType
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return FieldType
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return FieldType
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return array<mixed>
     */
    public function getDisplayOptions(): array
    {
        return $this->options[self::DISPLAY_OPTIONS] ?? [];
    }

    /**
     * @param array<mixed> $displayOptions
     */
    public function setDisplayExtraOptions(array $displayOptions): void
    {
        $this->displayExtraOptions = $displayOptions;
    }

    public function getDisplayOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getDisplayOptions();

        return $this->displayExtraOptions[$key] ?? $options[$key] ?? $default;
    }

    public function getDisplayBoolOption(string $key, bool $default): bool
    {
        return (bool) ($this->displayExtraOptions[$key] ?? $this->options[self::DISPLAY_OPTIONS][$key] ?? $default);
    }

    public function getMappingOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getMappingOptions();

        return $options[$key] ?? $default;
    }

    /**
     * @return array<mixed>
     */
    public function getMappingOptions(): array
    {
        $options = $this->getOptions();

        return $options['mappingOptions'] ?? [];
    }

    /**
     * @return array<mixed>
     */
    public function getRestrictionOptions(): array
    {
        $options = $this->getOptions();

        return $options['restrictionOptions'] ?? [];
    }

    public function getRestrictionOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getRestrictionOptions();

        return $options[$key] ?? $default;
    }

    public function getMigrationOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getMigrationOptions();

        return $options[$key] ?? $default;
    }

    /**
     * @return array<mixed>
     */
    public function getMigrationOptions(): array
    {
        $options = $this->getOptions();

        return $options['migrationOptions'] ?? [];
    }

    /**
     * @return array<mixed>
     */
    public function getExtraOptions(): array
    {
        $options = $this->getOptions();

        return $options['extraOptions'] ?? [];
    }

    public function getExtraOption(string $key, mixed $default = null): mixed
    {
        return $this->getExtraOptions()[$key] ?? $default;
    }

    public function getMinimumRole(): string
    {
        $options = $this->getOptions();
        if (isset($options['restrictionOptions']) && isset($options['restrictionOptions']['minimum_role'])) {
            return $options['restrictionOptions']['minimum_role'];
        }

        return 'ROLE_AUTHOR';
    }

    /**
     * @return FieldType[]
     */
    public function getValidChildren(): array
    {
        $valid = [];
        foreach ($this->children as $child) {
            if (!$child->getDeleted()) {
                $valid[] = $child;
            }
        }

        return $valid;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function isContainer(): bool
    {
        return $this->getType()::isContainer();
    }

    public function isJsonMenuNestedEditor(): bool
    {
        return JsonMenuNestedEditorFieldType::class === $this->getType();
    }

    public function isJsonMenuNestedEditorNode(): bool
    {
        $parent = $this->getParent();

        return $parent && JsonMenuNestedEditorFieldType::class === $parent->getType();
    }

    public function isJsonMenuNestedEditorField(): bool
    {
        if ($this->isJsonMenuNestedEditor()) {
            return true;
        }

        if (null !== $parent = $this->getParent()) {
            return $parent->isJsonMenuNestedEditorField();
        }

        return false;
    }

    public function getJsonMenuNestedEditor(): ?FieldType
    {
        if ($this->isJsonMenuNestedEditor()) {
            return $this;
        }

        if ($this->isJsonMenuNestedEditorNode()) {
            return $this->getParent();
        }

        return null;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return FieldType
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * Set contentType.
     *
     * @return FieldType
     */
    public function setContentType(?ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?ContentType
    {
        $parent = $this;
        while (null != $parent->parent) {
            $parent = $parent->parent;
        }

        return $parent->contentType;
    }

    public function giveContentType(): ContentType
    {
        $parent = $this;
        while (null != $parent->parent) {
            $parent = $parent->parent;
        }

        if (!$parent->contentType instanceof ContentType) {
            throw new \RuntimeException('Unexpected content type object');
        }

        return $parent->contentType;
    }

    public function get(string $key): FieldType
    {
        if (!\str_starts_with($key, 'ems_')) {
            throw new \Exception('unprotected ems get with key '.$key);
        }
        $key = \substr($key, 4);

        /** @var FieldType $fieldType */
        foreach ($this->getChildren() as $fieldType) {
            if (!$fieldType->getDeleted() && 0 === \strcmp($key, $fieldType->getName())) {
                return $fieldType;
            }
        }

        throw new \RuntimeException(\sprintf('Field type for key "%s" not found', $key));
    }

    public function setParent(?FieldType $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent(): ?FieldType
    {
        return $this->parent;
    }

    /**
     * @return array<string, FieldType>
     */
    private function listAllFields(): array
    {
        $out = [];
        foreach ($this->getChildren() as $child) {
            $out = [...$out, ...$child->listAllFields()];
        }
        $out['key_'.$this->getId()] = $this;

        return $out;
    }

    /**
     * @param array<mixed>             $newStructure
     * @param array<string, FieldType> $fieldsByIds
     */
    public function reorderFields(array $newStructure, ?array $fieldsByIds = null): void
    {
        if (null === $fieldsByIds) {
            $fieldsByIds = $this->listAllFields();
        }
        $this->getChildren()->clear();
        foreach ($newStructure as $key => $item) {
            if (\array_key_exists('key_'.$item['id'], $fieldsByIds)) {
                $this->getChildren()->add($fieldsByIds['key_'.$item['id']]);
                $fieldsByIds['key_'.$item['id']]->setParent($this);
                $fieldsByIds['key_'.$item['id']]->setOrderKey($key + 1);
                $fieldsByIds['key_'.$item['id']]->reorderFields($item['children'] ?? [], $fieldsByIds);
            } else {
                throw new \RuntimeException(\sprintf('Field %d not found', $item['id']));
            }
        }
    }

    public function addChild(FieldType $child, bool $prepend = false): self
    {
        $child->setParent($this);
        if ($prepend) {
            $children = $this->children->toArray();
            \array_unshift($children, $child);
            $this->children = new ArrayCollection($children);
        } else {
            $this->children[] = $child;
        }

        return $this;
    }

    public function removeChild(FieldType $child): void
    {
        $this->children->removeElement($child);
    }

    /**
     * @return Collection<int, FieldType>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPath(): string
    {
        if (null !== $parent = $this->getParent()) {
            $path = [\sprintf('[%s]', $this->getName())];
            \array_unshift($path, $parent->getPath());
        }

        return \implode('', $path ?? []);
    }

    /**
     * @return FieldType[]
     */
    public function getPathTypes(): array
    {
        $path = [$this];

        if (null !== $this->parent) {
            return \array_merge($this->parent->getPathTypes(), $path);
        }

        return $path;
    }

    public function getPropertyPath(): string
    {
        $pathTypes = $this->getPathTypes();
        \array_shift($pathTypes);
        $path = \array_map(static fn (FieldType $child) => $child->getName(), $pathTypes);

        return \sprintf('[%s]', \implode('][', $path));
    }

    public function findChildByName(string $name): ?FieldType
    {
        foreach ($this->loopChildren() as $child) {
            if (!$child->getDeleted() && $child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    public function findChildByPath(string $path): ?FieldType
    {
        foreach ($this->loopChildren() as $child) {
            if (!$child->getDeleted() && $child->getPath() === $path) {
                return $child;
            }
        }

        return null;
    }

    public function getChildByName(string $name): ?FieldType
    {
        foreach ($this->children as $child) {
            if ($child->getDeleted()) {
                continue;
            }
            if ($child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return \Generator|FieldType[]
     */
    public function loopChildren(): \Generator
    {
        foreach ($this->children as $child) {
            if ($child->getDeleted()) {
                continue;
            }
            yield $child;
            yield from $child->loopChildren();
        }
    }

    /**
     * @return string[]
     */
    public function getClearOnCopyPaths(): array
    {
        $result = [];

        foreach ($this->loopChildren() as $child) {
            $extraOptions = $child->getExtraOptions();
            $clearOnCopy = $extraOptions['clear_on_copy'] ?? false;

            if ($clearOnCopy) {
                $result[] = $child->getPath();
            }
        }

        return $result;
    }

    /**
     * @param array<mixed>|null $options
     */
    public function setOptions(?array $options): self
    {
        $this->options = $options ?? [];

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('parent');
        $json->removeProperty('displayExtraOptions');
        $json->updateProperty('children', $this->getValidChildren());

        return $json;
    }

    /**
     * @param mixed $value
     */
    #[\Override]
    protected function deserializeProperty(string $name, $value): void
    {
        switch ($name) {
            case 'children':
                foreach ($this->deserializeArray($value) as $child) {
                    $this->addChild($child);
                }
                break;
            default:
                parent::deserializeProperty($name, $value);
        }
    }

    public function filterDisplayOptions(DataFieldType $dataFieldType): void
    {
        $optionsResolver = new OptionsResolver();
        $dataFieldType->configureOptions($optionsResolver);
        $defineOptions = $optionsResolver->getDefinedOptions();
        $defineOptions[] = 'label';

        $filtered = \array_filter(
            $this->getDisplayOptions(),
            fn ($value) => \in_array($value, $defineOptions),
            ARRAY_FILTER_USE_KEY
        );
        $this->options[self::DISPLAY_OPTIONS] = $filtered;
    }
}
