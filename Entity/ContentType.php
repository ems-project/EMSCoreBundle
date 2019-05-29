<?php
namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;

/**
 * ContentType
 *
 * @ORM\Table(name="content_type")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\ContentTypeRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class ContentType
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=100)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="pluralName", type="string", length=100)
     */
    private $pluralName;

    /**
     * @var string
     *
     * @ORM\Column(name="singularName", type="string", length=100)
     */
    private $singularName;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="string", length=100, nullable=true)
     */
    private $icon;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;
    
    /**
     * @var string
     *
     * @ORM\Column(name="indexTwig", type="text", nullable=true)
     */
    private $indexTwig;

    /**
     * @var string
     *
     * @ORM\Column(name="extra", type="text", nullable=true)
     */
    private $extra;

    /**
     * @var string
     *
     * @ORM\Column(name="lockBy", type="string", length=100, nullable=true)
     */
    private $lockBy;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="lockUntil", type="datetime", nullable=true)
     */
    private $lockUntil;
    
    /**
     * @var string
     *
     * @ORM\Column(name="circles_field", type="string", length=100, nullable=true)
     */
    private $circlesField;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    private $deleted;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="have_pipelines", type="boolean", nullable=true)
     */
    private $havePipelines;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="ask_for_ouuid", type="boolean")
     */
    private $askForOuuid;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="dirty", type="boolean")
     */
    private $dirty;
    
    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    private $color;
    
    /**
     * @ORM\OneToOne(targetEntity="FieldType", cascade={"persist"})
     * @ORM\JoinColumn(name="field_types_id", referencedColumnName="id")
     */
    private $fieldType;
    
    /**
     * @var string
     *
     * @ORM\Column(name="labelField", type="string", length=100, nullable=true)
     */
    private $labelField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="color_field", type="string", length=100, nullable=true)
     */
    private $colorField;

    /**
     * @var string
     *
     * @ORM\Column(name="parentField", type="string", length=100, nullable=true)
     */
    private $parentField;

    /**
     * @var string
     *
     * @ORM\Column(name="userField", type="string", length=100, nullable=true)
     */
    private $userField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="dateField", type="string", length=100, nullable=true)
     */
    private $dateField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="startDateField", type="string", length=100, nullable=true)
     */
    private $startDateField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="endDateField", type="string", length=100, nullable=true)
     */
    private $endDateField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="locationField", type="string", length=100, nullable=true)
     */
    private $locationField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="referer_field_name", type="string", length=100, nullable=true)
     */
    private $refererFieldName;
    
    /**
     * @var string
     *
     * @ORM\Column(name="category_field", type="string", length=100, nullable=true)
     */
    private $categoryField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="ouuidField", type="string", length=100, nullable=true)
     */
    private $ouuidField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="imageField", type="string", length=100, nullable=true)
     */
    private $imageField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="videoField", type="string", length=100, nullable=true)
     */
    private $videoField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="email_field", type="string", length=100, nullable=true)
     */
    private $emailField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="asset_field", type="string", length=100, nullable=true)
     */
    private $assetField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="order_field", type="string", length=100, nullable=true)
     */
    private $orderField;
    
    /**
     * @var string
     *
     * @ORM\Column(name="sort_by", type="string", length=100, nullable=true)
     */
    private $sortBy;
    
    /**
     * @var string
     *
     * @ORM\Column(name="sort_order", type="string", length=4, nullable=true, options={"default" : "asc"})
     */
    private $sortOrder;
    
    /**
     * @var string
     *
     * @ORM\Column(name="create_role", type="string", length=100, nullable=true)
     */
    private $createRole;
    
    /**
     * @var string
     *
     * @ORM\Column(name="edit_role", type="string", length=100, nullable=true)
     */
    private $editRole;
    
    /**
     * @var string
     *
     * @ORM\Column(name="view_role", type="string", length=100, nullable=true)
     */
    private $viewRole;
    
    /**
     * @var string
     *
     * @ORM\Column(name="publish_role", type="string", length=100, nullable=true)
     */
    private $publishRole;
    
    /**
     * @var string
     *
     * @ORM\Column(name="trash_role", type="string", length=100, nullable=true)
     */
    private $trashRole;
    
    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    private $orderKey;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="rootContentType", type="boolean")
     */
    private $rootContentType;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="edit_twig_with_wysiwyg", type="boolean")
     */
    private $editTwigWithWysiwyg;

    /**
     * @var bool
     *
     * @ORM\Column(name="web_content", type="boolean", options={"default" : 1})
     */
    private $webContent;

    /**
     * @var bool
     *
     * @ORM\Column(name="auto_publish", type="boolean", options={"default" : 0})
     */
    private $autoPublish;
    
    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean")
     */
    private $active;

    /**
     * @ORM\ManyToOne(targetEntity="Environment", inversedBy="contentTypesHavingThisAsDefault")
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    private $environment;
    
    /**
     * @ORM\OneToMany(targetEntity="Template", mappedBy="contentType", cascade={"persist", "remove"})
     * @ORM\OrderBy({"orderKey" = "ASC"})
     */
    private $templates;

    /**
     * @ORM\OneToMany(targetEntity="View", mappedBy="contentType", cascade={"persist", "remove"})
     * @ORM\OrderBy({"orderKey" = "ASC"})
     */
    private $views;

    /**
     * @ORM\OneToMany(targetEntity="SingleTypeIndex", mappedBy="contentType", cascade={"persist", "remove"})
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $singleTypeIndexes;

    /**
     * @var string
     * @ORM\Column(name="default_value", type="text", nullable=true)
     */
    public $defaultValue;


    public function __construct()
    {

        $this->templates = new \Doctrine\Common\Collections\ArrayCollection();
        $this->views = new \Doctrine\Common\Collections\ArrayCollection();
        $this->singleTypeIndexes = new \Doctrine\Common\Collections\ArrayCollection();
        
        $this->dirty = true;
        $this->editTwigWithWysiwyg = true;
        $this->webContent = true;
        $this->autoPublish = false;

         $fieldType = new FieldType();
         $fieldType->setName('source');
         $fieldType->setType(ContainerFieldType::class);
         $fieldType->setContentType($this);
         $this->setFieldType($fieldType);
         $this->setAskForOuuid(true);
    }



    public function __toString()
    {
        return $this->name;
    }
    
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
        if (!isset($this->deleted)) {
            $this->deleted = false;
        }
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
        }
        if (!isset($this->rootContentType)) {
            $this->rootContentType = true;
        }
        if (!isset($this->active)) {
            $this->active = false;
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
     * @return ContentType
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
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
     * @return ContentType
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
     * Set name
     *
     * @param string $name
     *
     * @return ContentType
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
     * Set icon
     *
     * @param string $icon
     *
     * @return ContentType
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
    
        return $this;
    }
    
    /**
     * Get icon
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    
    /**
     * Set description
     *
     * @param string $description
     *
     * @return ContentType
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

    /**
     * Set lockBy
     *
     * @param string $lockBy
     *
     * @return ContentType
     */
    public function setLockBy($lockBy)
    {
        $this->lockBy = $lockBy;

        return $this;
    }

    /**
     * Get lockBy
     *
     * @return string
     */
    public function getLockBy()
    {
        return $this->lockBy;
    }

    /**
     * Set lockUntil
     *
     * @param \DateTime $lockUntil
     *
     * @return ContentType
     */
    public function setLockUntil($lockUntil)
    {
        $this->lockUntil = $lockUntil;

        return $this;
    }

    /**
     * Get lockUntil
     *
     * @return \DateTime
     */
    public function getLockUntil()
    {
        return $this->lockUntil;
    }

    /**
     * Set deleted
     *
     * @param boolean $deleted
     *
     * @return ContentType
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted
     *
     * @return boolean
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return ContentType
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set labelField
     *
     * @param string $labelField
     *
     * @return ContentType
     */
    public function setLabelField($labelField)
    {
        $this->labelField = $labelField;

        return $this;
    }

    /**
     * Get labelField
     *
     * @return string
     */
    public function getLabelField()
    {
        return $this->labelField;
    }

    /**
     * Set parentField
     *
     * @param string $parentField
     *
     * @return ContentType
     */
    public function setParentField($parentField)
    {
        $this->parentField = $parentField;

        return $this;
    }

    /**
     * Get parentField
     *
     * @return string
     */
    public function getParentField()
    {
        return $this->parentField;
    }

    /**
     * Set dateField
     *
     * @param string $dateField
     *
     * @return ContentType
     */
    public function setDateField($dateField)
    {
        $this->dateField = $dateField;

        return $this;
    }

    /**
     * Get dateField
     *
     * @return string
     */
    public function getDateField()
    {
        return $this->dateField;
    }

    /**
     * Set endDateField
     *
     * @param string $endDateField
     *
     * @return ContentType
     */
    public function setEndDateField($endDateField)
    {
        $this->endDateField = $endDateField;

        return $this;
    }

    /**
     * Get endDateField
     *
     * @return string
     */
    public function getEndDateField()
    {
        return $this->endDateField;
    }

    /**
     * Set locationField
     *
     * @param string $locationField
     *
     * @return ContentType
     */
    public function setLocationField($locationField)
    {
        $this->locationField = $locationField;

        return $this;
    }

    /**
     * Get locationField
     *
     * @return string
     */
    public function getLocationField()
    {
        return $this->locationField;
    }

    /**
     * Set ouuidField
     *
     * @param string $ouuidField
     *
     * @return ContentType
     */
    public function setOuuidField($ouuidField)
    {
        $this->ouuidField = $ouuidField;

        return $this;
    }

    /**
     * Get ouuidField
     *
     * @return string
     */
    public function getOuuidField()
    {
        return $this->ouuidField;
    }

    /**
     * Set imageField
     *
     * @param string $imageField
     *
     * @return ContentType
     */
    public function setImageField($imageField)
    {
        $this->imageField = $imageField;

        return $this;
    }

    /**
     * Get imageField
     *
     * @return string
     */
    public function getImageField()
    {
        return $this->imageField;
    }

    /**
     * Set videoField
     *
     * @param string $videoField
     *
     * @return ContentType
     */
    public function setVideoField($videoField)
    {
        $this->videoField = $videoField;

        return $this;
    }

    /**
     * Get videoField
     *
     * @return string
     */
    public function getVideoField()
    {
        return $this->videoField;
    }

    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return ContentType
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey
     *
     * @return integer
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * Set rootContentType
     *
     * @param boolean $rootContentType
     *
     * @return ContentType
     */
    public function setRootContentType($rootContentType)
    {
        $this->rootContentType = $rootContentType;

        return $this;
    }

    /**
     * Get rootContentType
     *
     * @return boolean
     */
    public function getRootContentType()
    {
        return $this->rootContentType;
    }

    /**
     * Set pluralName
     *
     * @param string $pluralName
     *
     * @return ContentType
     */
    public function setPluralName($pluralName)
    {
        $this->pluralName = $pluralName;

        return $this;
    }

    /**
     * Get pluralName
     *
     * @return string
     */
    public function getPluralName()
    {
        return $this->pluralName;
    }

    /**
     * Set startDateField
     *
     * @param string $startDateField
     *
     * @return ContentType
     */
    public function setStartDateField($startDateField)
    {
        $this->startDateField = $startDateField;

        return $this;
    }

    /**
     * Get startDateField
     *
     * @return string
     */
    public function getStartDateField()
    {
        return $this->startDateField;
    }

    /**
     * Set userField
     *
     * @param string $userField
     *
     * @return ContentType
     */
    public function setUserField($userField)
    {
        $this->userField = $userField;

        return $this;
    }

    /**
     * Get userField
     *
     * @return string
     */
    public function getUserField()
    {
        return $this->userField;
    }

    /**
     * Set indexTwig
     *
     * @param string $indexTwig
     *
     * @return ContentType
     */
    public function setIndexTwig($indexTwig)
    {
        $this->indexTwig = $indexTwig;

        return $this;
    }

    /**
     * Get indexTwig
     *
     * @return string
     */
    public function getIndexTwig()
    {
        return $this->indexTwig;
    }

    /**
     * Set extra
     *
     * @param string $extra
     *
     * @return ContentType
     */
    public function setExtra($extra)
    {
        $this->extra = $extra;

        return $this;
    }

    /**
     * Get extra
     *
     * @return string
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * Get fieldType
     *
     * @return FieldType
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * Set active
     *
     * @param boolean $active
     *
     * @return ContentType
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set fieldType
     *
     * @param \EMS\CoreBundle\Entity\FieldType $fieldType
     *
     * @return ContentType
     */
    public function setFieldType(\EMS\CoreBundle\Entity\FieldType $fieldType = null)
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    /**
     * Unset fieldType
     *
     * @return ContentType
     */
    public function unsetFieldType()
    {
        $this->fieldType = null;

        return $this;
    }

    /**
     * Set environment
     *
     * @param \EMS\CoreBundle\Entity\Environment $environment
     *
     * @return ContentType
     */
    public function setEnvironment(\EMS\CoreBundle\Entity\Environment $environment = null)
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get environment
     *
     * @return \EMS\CoreBundle\Entity\Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Set categoryField
     *
     * @param string $categoryField
     *
     * @return ContentType
     */
    public function setCategoryField($categoryField)
    {
        $this->categoryField = $categoryField;

        return $this;
    }

    /**
     * Get categoryField
     *
     * @return string
     */
    public function getCategoryField()
    {
        return $this->categoryField;
    }

    /**
     * Set dirty
     *
     * @param boolean $dirty
     *
     * @return ContentType
     */
    public function setDirty($dirty)
    {
        $this->dirty = $dirty;

        return $this;
    }

    /**
     * Get dirty
     *
     * @return boolean
     */
    public function getDirty()
    {
        return $this->dirty;
    }
    
    /**
     * Set editTwigWithWysiwyg
     *
     * @param boolean $editTwigWithWysiwyg
     *
     * @return ContentType
     */
    public function setEditTwigWithWysiwyg($editTwigWithWysiwyg)
    {
        $this->editTwigWithWysiwyg = $editTwigWithWysiwyg;
        
        return $this;
    }
    
    /**
     * Get editTwigWithWysiwyg
     *
     * @return boolean
     */
    public function getEditTwigWithWysiwyg()
    {
        return $this->editTwigWithWysiwyg;
    }
    
    /**
     * Set webContent
     *
     * @param boolean $webContent
     *
     * @return ContentType
     */
    public function setWebContent($webContent)
    {
        $this->webContent= $webContent;
        
        return $this;
    }
    
    /**
     * Get webContent
     *
     * @return boolean
     */
    public function getWebContent()
    {
        return $this->webContent;
    }

    /**
     * Add template
     *
     * @param \EMS\CoreBundle\Entity\Template $template
     *
     * @return ContentType
     */
    public function addTemplate(\EMS\CoreBundle\Entity\Template $template)
    {
        $this->templates[] = $template;

        return $this;
    }

    /**
     * Remove template
     *
     * @param \EMS\CoreBundle\Entity\Template $template
     */
    public function removeTemplate(\EMS\CoreBundle\Entity\Template $template)
    {
        $this->templates->removeElement($template);
    }

    /**
     * Get templates
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * Add view
     *
     * @param \EMS\CoreBundle\Entity\View $view
     *
     * @return ContentType
     */
    public function addView(\EMS\CoreBundle\Entity\View $view)
    {
        $this->views[] = $view;

        return $this;
    }

    /**
     * Remove view
     *
     * @param \EMS\CoreBundle\Entity\View $view
     */
    public function removeView(\EMS\CoreBundle\Entity\View $view)
    {
        $this->views->removeElement($view);
    }

    /**
     * Get views
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * Set askForOuuid
     *
     * @param boolean $askForOuuid
     *
     * @return ContentType
     */
    public function setAskForOuuid($askForOuuid)
    {
        $this->askForOuuid = $askForOuuid;

        return $this;
    }

    /**
     * Get askForOuuid
     *
     * @return boolean
     */
    public function getAskForOuuid()
    {
        return $this->askForOuuid;
    }

    /**
     * Set colorField
     *
     * @param string $colorField
     *
     * @return ContentType
     */
    public function setColorField($colorField)
    {
        $this->colorField = $colorField;

        return $this;
    }

    /**
     * Get colorField
     *
     * @return string
     */
    public function getColorField()
    {
        return $this->colorField;
    }

    /**
     * Set circlesField
     *
     * @param string $circlesField
     *
     * @return ContentType
     */
    public function setCirclesField($circlesField)
    {
        $this->circlesField = $circlesField;
    
        return $this;
    }

    /**
     * Get circlesField
     *
     * @return string
     */
    public function getCirclesField()
    {
        return $this->circlesField;
    }

    /**
     * Set emailField
     *
     * @param string $emailField
     *
     * @return ContentType
     */
    public function setEmailField($emailField)
    {
        $this->emailField = $emailField;

        return $this;
    }

    /**
     * Get emailField
     *
     * @return string
     */
    public function getEmailField()
    {
        return $this->emailField;
    }

    /**
     * Set createRole
     *
     * @param string $createRole
     *
     * @return ContentType
     */
    public function setCreateRole($createRole)
    {
        $this->createRole = $createRole;

        return $this;
    }

    /**
     * Get createRole
     *
     * @return string
     */
    public function getCreateRole()
    {
        return $this->createRole;
    }

    /**
     * Set editRole
     *
     * @param string $editRole
     *
     * @return ContentType
     */
    public function setEditRole($editRole)
    {
        $this->editRole = $editRole;

        return $this;
    }

    /**
     * Get editRole
     *
     * @return string
     */
    public function getEditRole()
    {
        return $this->editRole;
    }

    /**
     * Set assetField
     *
     * @param string $assetField
     *
     * @return ContentType
     */
    public function setAssetField($assetField)
    {
        $this->assetField = $assetField;

        return $this;
    }

    /**
     * Get assetField
     *
     * @return string
     */
    public function getAssetField()
    {
        return $this->assetField;
    }

    /**
     * Set orderField
     *
     * @param string $orderField
     *
     * @return ContentType
     */
    public function setOrderField($orderField)
    {
        $this->orderField = $orderField;

        return $this;
    }

    /**
     * Get orderField
     *
     * @return string
     */
    public function getOrderField()
    {
        return $this->orderField;
    }

    /**
     * Set sortBy
     *
     * @param string $sortBy
     *
     * @return ContentType
     */
    public function setSortBy($sortBy)
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    /**
     * Get sortBy
     *
     * @return string
     */
    public function getSortBy()
    {
        return $this->sortBy;
    }

    /**
     * Set refererFieldName
     *
     * @param string $refererFieldName
     *
     * @return ContentType
     */
    public function setRefererFieldName($refererFieldName)
    {
        $this->refererFieldName = $refererFieldName;

        return $this;
    }

    /**
     * Get refererFieldName
     *
     * @return string
     */
    public function getRefererFieldName()
    {
        return $this->refererFieldName;
    }
    
    /**
     * Set viewRole
     *
     * @param string $viewRole
     *
     * @return ContentType
     */
    public function setViewRole($viewRole)
    {
        $this->viewRole = $viewRole;
        
        return $this;
    }
    
    /**
     * Get viewRole
     *
     * @return string
     */
    public function getViewRole()
    {
        return $this->viewRole;
    }
    
    /**
     * Set publishRole
     *
     * @param string $publishRole
     *
     * @return ContentType
     */
    public function setPublishRole($publishRole)
    {
        $this->publishRole = $publishRole;
        
        return $this;
    }
    
    /**
     * Get publishRole
     *
     * @return string
     */
    public function getPublishRole()
    {
        return $this->publishRole;
    }
    
    /**
     * Set trashRole
     *
     * @param string $trashRole
     *
     * @return ContentType
     */
    public function setTrashRole($trashRole)
    {
        $this->trashRole= $trashRole;
        
        return $this;
    }
    
    /**
     * Get trashRole
     *
     * @return string
     */
    public function getTrashRole()
    {
        return $this->trashRole;
    }

    /**
     * Set havePipelines
     *
     * @param boolean $havePipelines
     *
     * @return ContentType
     */
    public function setHavePipelines($havePipelines)
    {
        $this->havePipelines = $havePipelines;

        return $this;
    }

    /**
     * Get havePipelines
     *
     * @return boolean
     */
    public function getHavePipelines()
    {
        return $this->havePipelines;
    }

    /**
     * Set singularName
     *
     * @param string $singularName
     *
     * @return ContentType
     */
    public function setSingularName($singularName)
    {
        $this->singularName = $singularName;

        return $this;
    }

    /**
     * Get singularName
     *
     * @return string
     */
    public function getSingularName()
    {
        return $this->singularName;
    }

    public function setSortOrder(?string $sortOrder): ContentType
    {
        $this->sortOrder = $sortOrder;
        
        return $this;
    }

    public function getSortOrder(): ?string
    {
        return $this->sortOrder;
    }

    /**
     * Add single type index
     *
     * @param SingleTypeIndex $index
     *
     * @return ContentType
     */
    public function addSingleTypeIndex(SingleTypeIndex $index)
    {
        $this->singleTypeIndexes[] = $index;

        return $this;
    }

    /**
     * Remove single type index
     *
     * @param SingleTypeIndex $index
     */
    public function removeSingleTypeIndex(SingleTypeIndex $index)
    {
        $this->singleTypeIndexes->removeElement($index);
    }

    /**
     * Get single type indexes
     *
     * @return Collection
     */
    public function getSingleTypeIndexes()
    {
        return $this->singleTypeIndexes;
    }

    /**
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string $defaultValue
     * @return ContentType
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAutoPublish(): bool
    {
        return $this->autoPublish;
    }

    /**
     * @param bool $autoPublish
     * @return ContentType
     */
    public function setAutoPublish(bool $autoPublish): ContentType
    {
        $this->autoPublish = $autoPublish;
        return $this;
    }
}
