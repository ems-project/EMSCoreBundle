<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FieldType.
 *
 * @ORM\Table(name="field_type")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\FieldTypeRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class FieldType extends JsonDeserializer implements \JsonSerializable
{
    public const DISPLAY_OPTIONS = 'displayOptions';
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    protected $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    protected $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\OneToOne(targetEntity="ContentType")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    protected $contentType;

    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    protected $deleted;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var array
     *
     * @ORM\Column(name="options", type="json_array", nullable=true)
     */
    protected $options;

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected $orderKey;

    /**
     * @var FieldType
     *
     * @ORM\ManyToOne(targetEntity="FieldType", inversedBy="children", cascade={"persist"})
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var ArrayCollection|FieldType[]
     * @ORM\OneToMany(targetEntity="FieldType", mappedBy="parent", cascade={"persist", "remove"})
     * @ORM\OrderBy({"orderKey" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    /**
     * Update contentType and parent recursively.
     */
    //TODO: Unrecursify this method
    public function updateAncestorReferences($contentType, $parent)
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

    /**
     * Set created.
     *
     * @param \DateTime $created
     *
     * @return FieldType
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    public function updateOrderKeys()
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
    public function removeCircularReference()
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
     * set the data value(s) from a string received from the symfony form) in the context of this field.
     *
     * @return \DateTime
     */
    public function setDataValue($input, DataField &$dataField)
    {
        throw new \Exception('Deprecated method');
//         $type = $this->getType();
//         /** @var DataFieldType $dataFieldType */
//         $dataFieldType = new $type;

//         $dataFieldType->setDataValue($input, $dataField, $this->getOptions());
    }

    public function getFieldsRoles()
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
     * get the data value(s) as a string received for the symfony form) in the context of this field.
     *
     * @return \DateTime
     */
    public function getDataValue(DataField &$dataField)
    {
        throw new \Exception('Deprecated method');
//         $type = $this->getType();
//         /** @var DataFieldType $dataFieldType */
//         $dataFieldType = new $type;

//         return $dataFieldType->getDataValue($dataField, $this->getOptions());
    }

    /**
     * Get created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified.
     *
     * @param \DateTime $modified
     *
     * @return FieldType
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }

    /**
     * Get modified.
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return FieldType
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
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

    public function getDisplayOption($key, $default = null)
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

    public function getMappingOption($key, $default = null)
    {
        $options = $this->getMappingOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    public function getMappingOptions()
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
     * @param mixed $default
     *
     * @return mixed
     */
    public function getRestrictionOption(string $key, $default = null)
    {
        $options = $this->getRestrictionOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getMigrationOption(string $key, $default = null)
    {
        $options = $this->getMigrationOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }

        return $default;
    }

    public function getMigrationOptions()
    {
        $options = $this->getOptions();
        if (isset($options['migrationOptions'])) {
            return $options['migrationOptions'];
        }

        return [];
    }

    public function getExtraOptions()
    {
        $options = $this->getOptions();
        if (isset($options['extraOptions'])) {
            return $options['extraOptions'];
        }

        return [];
    }

    public function getMinimumRole()
    {
        $options = $this->getOptions();
        if (isset($options['restrictionOptions']) && isset($options['restrictionOptions']['minimum_role'])) {
            return $options['restrictionOptions']['minimum_role'];
        }

        return 'ROLE_AUTHOR';
    }

    /**
     * Get only valid children.
     *
     * @return array
     */
    public function getValidChildren()
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
     * @return array<mixed>
     */
    public function getJsonMenuNestedEditorNodes(): array
    {
        $nodes = [];

        if (null === $jsonMenuNestedEditor = $this->getJsonMenuNestedEditor()) {
            return $nodes;
        }

        foreach ($jsonMenuNestedEditor->children as $child) {
            if ($child->getDeleted() || !$child->getType()::isContainer()) {
                continue;
            }

            $nodes[$child->getName()] = [
                'id' => $child->getId(),
                'name' => $child->getName(),
                'minimumRole' => $child->getRestrictionOption('minimum_role', null),
                'label' => $child->getDisplayOption('label', $child->getName()),
                'icon' => $child->getDisplayOption('icon', null),
                'deny' => \array_merge(['root'], $child->getRestrictionOption('json_nested_deny', [])),
                'isLeaf' => $child->getRestrictionOption('json_nested_is_leaf', false),
            ];
        }

        return $nodes;
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
     * @param ContentType $contentType
     *
     * @return FieldType
     */
    public function setContentType(ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get contentType.
     *
     * @return ContentType|null
     */
    public function getContentType()
    {
        $parent = $this;
        while (null != $parent->parent) {
            $parent = $parent->parent;
        }

        return $parent->contentType;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->deleted = false;
        $this->orderKey = 0;
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
     * @return FieldType|null
     */
    public function __get($key)
    {
        if (0 !== \strpos($key, 'ems_')) {
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

    /**
     * set a child.
     *
     * @throws \Exception
     *
     * @return FieldType
     */
    public function __set($key, $input)
    {
        if (0 !== \strpos($key, 'ems_')) {
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

        return $this;
    }

    /**
     * Set parent.
     *
     * @param \EMS\CoreBundle\Entity\FieldType $parent
     *
     * @return FieldType
     */
    public function setParent(FieldType $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \EMS\CoreBundle\Entity\FieldType
     */
    public function getParent(): ?FieldType
    {
        return $this->parent;
    }

    /**
     * Add child.
     *
     * @return FieldType
     */
    public function addChild(FieldType $child, bool $prepend = false)
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

    /**
     * Remove child.
     */
    public function removeChild(FieldType $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children.
     *
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return \Generator|FieldType[]
     */
    public function loopChildren(): \Generator
    {
        foreach ($this->children as $child) {
            yield $child;
            yield from $child->loopChildren();
        }
    }

    /**
     * Get child by path.
     *
     * @return FieldType|false
     *
     * @deprecated it's not clear if its the mapping of the rawdata or of the formdata (with ou without the virtual fields) see the same function in the contenttypeservice
     */
    public function getChildByPath($path)
    {
        $elem = \explode('.', $path);
        if (!empty($elem)) {
            /** @var FieldType $child */
            foreach ($this->children as $child) {
                if (!$child->getDeleted() && $child->getName() == $elem[0]) {
                    if (\strpos($path, '.')) {
                        return $child->getChildByPath(\substr($path, \strpos($path, '.') + 1));
                    }

                    return $child;
                }
            }
        }

        return false;
    }

    /**
     * Set options.
     *
     * @param array $options
     *
     * @return FieldType
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @see https://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
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

    public function filterDisplayOptions(DataFieldType $dataFieldType)
    {
        $optionsResolver = new OptionsResolver();
        $dataFieldType->configureOptions($optionsResolver);
        $defineOptions = $optionsResolver->getDefinedOptions();
        $defineOptions[] = 'label';

        $filtered = \array_filter(
            $this->getDisplayOptions(),
            function ($value) use ($defineOptions) {
                return \in_array($value, $defineOptions);
            },
            ARRAY_FILTER_USE_KEY
        );
        $this->options[self::DISPLAY_OPTIONS] = $filtered;
    }
}
