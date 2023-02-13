<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FieldType.
 *
 * @ORM\Table(name="field_type")
 *
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\FieldTypeRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class FieldType extends JsonDeserializer implements \JsonSerializable
{
    use CreatedModifiedTrait;
    final public const DISPLAY_OPTIONS = 'displayOptions';
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var class-string<DataFieldType>
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    protected string $type;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\OneToOne(targetEntity="ContentType")
     *
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    protected ?ContentType $contentType = null;

    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    protected $deleted = false;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var array<mixed>|null
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    protected array|null $options = [];

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected $orderKey = 0;

    /**
     * @var ?FieldType
     *
     * @ORM\ManyToOne(targetEntity="FieldType", inversedBy="children", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected ?FieldType $parent = null;

    /**
     * @var Collection<int, FieldType>
     *
     * @ORM\OneToMany(targetEntity="FieldType", mappedBy="parent", cascade={"persist", "remove"})
     *
     * @ORM\OrderBy({"orderKey" = "ASC"})
     */
    protected Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
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

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
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
            foreach ($this->children as $key => $child) {
                $child->removeCircularReference();
            }
            $this->setContentType(null);
            $this->setParent(null);
        }
    }

    /**
     * @param mixed $input
     *
     * set the data value(s) from a string received from the symfony form) in the context of this field
     */
    public function setDataValue(mixed $input, DataField &$dataField): never
    {
        throw new \Exception('Deprecated method');
//         $type = $this->getType();
//         /** @var DataFieldType $dataFieldType */
//         $dataFieldType = new $type;

//         $dataFieldType->setDataValue($input, $dataField, $this->getOptions());
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

    public function getDataValue(DataField &$dataField): never
    {
        throw new \Exception('Deprecated method');
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
     * @param ?mixed $default
     *
     * @return mixed
     */
    public function getDisplayOption(string $key, $default = null)
    {
        $options = $this->getDisplayOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    public function getDisplayBoolOption(string $key, bool $default): bool
    {
        return \boolval($this->options[self::DISPLAY_OPTIONS][$key] ?? $default);
    }

    /**
     * @param ?mixed $default
     *
     * @return mixed
     */
    public function getMappingOption(string $key, $default = null)
    {
        $options = $this->getMappingOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @return array<mixed>
     */
    public function getMappingOptions(): array
    {
        $options = $this->getOptions();
        if (isset($options['mappingOptions'])) {
            return $options['mappingOptions'];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    public function getRestrictionOptions(): array
    {
        $options = $this->getOptions();
        if (isset($options['restrictionOptions'])) {
            return $options['restrictionOptions'];
        }

        return [];
    }

    /**
     * @return mixed
     */
    public function getRestrictionOption(string $key, mixed $default = null)
    {
        $options = $this->getRestrictionOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @return mixed
     */
    public function getMigrationOption(string $key, mixed $default = null)
    {
        $options = $this->getMigrationOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @return array<mixed>
     */
    public function getMigrationOptions(): array
    {
        $options = $this->getOptions();
        if (isset($options['migrationOptions'])) {
            return $options['migrationOptions'];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    public function getExtraOptions(): array
    {
        $options = $this->getOptions();
        if (isset($options['extraOptions'])) {
            return $options['extraOptions'];
        }

        return [];
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
    public function setContentType(ContentType $contentType = null)
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

//     /**
//      * Cette focntion clone casse le CollectionFieldType => impossible d'ajouter un record
//      */
//     public function __clone()
//     {
//         $this->children = new \Doctrine\Common\Collections\ArrayCollection ();
//         $this->deleted = $this->deleted;
//         $this->orderKey = $this->orderKey;
//         $this->created = null;
//         $this->modified = null;
//         $this->description = $this->description;
//         $this->id = 0;
//         $this->name = $this->name ;
//         $this->options = $this->options;
//         $this->type = $this->type;
//     }

    /**
     * get a child.
     *
     * @throws \Exception
     *
     * @deprecated Use FieldType->get($key)
     */
    public function __get(string $key): ?FieldType
    {
        if (!\str_starts_with($key, 'ems_')) {
            throw new \Exception('unprotected ems get with key '.$key);
        } else {
            $key = \substr($key, 4);
        }
        /** @var FieldType $fieldType */
        foreach ($this->getChildren() as $fieldType) {
            if (!$fieldType->getDeleted() && 0 == \strcmp($key, $fieldType->getName())) {
                return $fieldType;
            }
        }

        return null;
    }

    public function get(string $key): FieldType
    {
        if (null === $fieldType = $this->__get($key)) {
            throw new \RuntimeException(\sprintf('Field type for key "%s" not found', $key));
        }

        return $fieldType;
    }

    /**
     * @throws \Exception
     */
    public function __set(string $key, mixed $input): void
    {
        if (!\str_starts_with($key, 'ems_')) {
            throw new \Exception('unprotected ems set with key '.$key);
        } else {
            $key = \substr($key, 4);
        }
        $found = false;
        /** @var FieldType $child */
        foreach ($this->children as &$child) {
            if (!$child->getDeleted() && 0 == \strcmp($key, $child->getName())) {
                $found = true;
                $child = $input;
                break;
            }
        }
        if (!$found) {
            $this->children->add($input);
        }
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

    public function addChild(FieldType $child, bool $prepend = false): self
    {
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

    public function findChildByName(string $name): ?FieldType
    {
        foreach ($this->loopChildren() as $child) {
            if (!$child->getDeleted() && $child->getName() === $name) {
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
     * Get child by path.
     *
     * @deprecated it's not clear if its the mapping of the rawdata or of the formdata (with ou without the virtual fields) see the same function in the contenttypeservice
     */
    public function getChildByPath(string $path): FieldType|false
    {
        $elem = \explode('.', $path);

        /** @var FieldType $child */
        foreach ($this->children as $child) {
            if (!$child->getDeleted() && $child->getName() == $elem[0]) {
                if (\strpos($path, '.')) {
                    return $child->getChildByPath(\substr($path, \strpos($path, '.') + 1));
                }

                return $child;
            }
        }

        return false;
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

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->updateProperty('children', $this->getValidChildren());

        return $json;
    }

    /**
     * @param mixed $value
     */
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
