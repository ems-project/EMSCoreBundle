<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * FieldType
 *
 * @ORM\Table(name="field_type")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\FieldTypeRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class FieldType extends JsonDeserializer implements \JsonSerializable
{
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
     * @var ArrayCollection
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
     * Update contentType and parent recursively
     *
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
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

/**
     * Set created
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
     * Remove references to parent to prevent circular reference exception
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
     * set the data value(s) from a string received from the symfony form) in the context of this field
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
            $out = array_merge($out, $child->getFieldsRoles());
        }
        return $out;
    }
    
    /**
     * get the data value(s) as a string received for the symfony form) in the context of this field
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
     * Get created
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set modified
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
     * Get modified
     *
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set type
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
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set name
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
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set deleted
     *
     * @param boolean $deleted
     *
     * @return FieldType
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set description
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
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function getDisplayOptions()
    {
        $options = $this->getOptions();
        if (isset($options['displayOptions'])) {
            return $options['displayOptions'];
        }
        return [];
    }
    
    
    public function getDisplayOption($key, $default = null)
    {
        $options = $this->getDisplayOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }
        return $default;
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

    public function getRestrictionOptions()
    {
        $options = $this->getOptions();
        if (isset($options['restrictionOptions'])) {
            return $options['restrictionOptions'];
        }
        return [];
    }
    
    
    
    public function getMigrationgOption($key, $default = null)
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
     * Get only valid children
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
    
    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return FieldType
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * Set contentType
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
     * Get contentType
     *
     * @return null|ContentType
     */
    public function getContentType()
    {
        $parent = $this;
        while ($parent->parent != null) {
            $parent = $parent->parent;
        }
        return $parent->contentType;
    }
    
    /**
     * Constructor
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
     * get a child
     *
     * @throws \Exception
     *
     * @return FieldType|null
     */
    public function __get($key)
    {
        if (strpos($key, 'ems_') !== 0) {
             throw new \Exception('unprotected ems get with key ' . $key);
        } else {
            $key = substr($key, 4);
        }
        /** @var FieldType $fieldType */
        foreach ($this->getChildren() as $fieldType) {
            if (!$fieldType->getDeleted() && strcmp($key, $fieldType->getName()) == 0) {
                return $fieldType;
            }
        }
    
        return null;
    }

    /**
     * set a child
     *
     * @throws \Exception
     *
     * @return FieldType
     */
    public function __set($key, $input)
    {
        if (strpos($key, 'ems_') !== 0) {
             throw new \Exception('unprotected ems set with key ' . $key);
        } else {
            $key = substr($key, 4);
        }
        $found = false;
        /** @var FieldType $child */
        foreach ($this->children as &$child) {
            if (!$child->getDeleted() && strcmp($key, $child->getName()) == 0) {
                $found = true;
                $child = $input;
                break;
            }
        }
        if (! $found) {
            $this->children->add($input);
        }
         
        return $this;
    }
    

    
    /**
     * Set parent
     *
     * @param \EMS\CoreBundle\Entity\FieldType $parent
     *
     * @return FieldType
     */
    public function setParent(\EMS\CoreBundle\Entity\FieldType $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \EMS\CoreBundle\Entity\FieldType
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add child
     *
     * @param \EMS\CoreBundle\Entity\FieldType $child
     *
     * @return FieldType
     */
    public function addChild(\EMS\CoreBundle\Entity\FieldType $child)
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Remove child
     *
     * @param \EMS\CoreBundle\Entity\FieldType $child
     */
    public function removeChild(\EMS\CoreBundle\Entity\FieldType $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Get child by path
     *
     * @return FieldType|false
     *
     * @deprecated it's not clear if its the mapping of the rawdata or of the formdata (with ou without the virtual fields) see the same function in the contenttypeservice
     */
    public function getChildByPath($path)
    {
        $elem = explode('.', $path);
        if (!empty($elem)) {
            /**@var FieldType $child*/
            foreach ($this->children as $child) {
                if (!$child->getDeleted() && $child->getName() == $elem[0]) {
                    if (strpos($path, ".")) {
                        return $child->getChildByPath(substr($path, strpos($path, ".") + 1));
                    }
                    return $child;
                }
            }
        }
        return false;
    }

    /**
     * Set options
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
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = new JsonClass(get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->updateProperty('children', $this->getValidChildren());

        return $json;
    }

    protected function deserializeProperty(string $name, $value)
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

        $filtered = array_filter(
            $this->getDisplayOptions(),
            function ($value) use ($defineOptions) {
                return in_array($value, $defineOptions);
            },
            ARRAY_FILTER_USE_KEY
        );
        $this->options['displayOptions'] = $filtered;
    }
}
