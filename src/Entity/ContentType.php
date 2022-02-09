<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;

/**
 * ContentType.
 *
 * @ORM\Table(name="content_type")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\ContentTypeRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class ContentType extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint")
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
     * @ORM\Column(name="name", type="string", length=100)
     */
    protected string $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="pluralName", type="string", length=100)
     */
    protected $pluralName;

    /**
     * @var string
     *
     * @ORM\Column(name="singularName", type="string", length=100)
     */
    protected $singularName;

    /**
     * @var string|null
     *
     * @ORM\Column(name="icon", type="string", length=100, nullable=true)
     */
    protected $icon;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="indexTwig", type="text", nullable=true)
     */
    protected $indexTwig;

    /**
     * @var string
     *
     * @ORM\Column(name="extra", type="text", nullable=true)
     */
    protected $extra;

    /**
     * @var string
     *
     * @ORM\Column(name="lockBy", type="string", length=100, nullable=true)
     */
    protected $lockBy;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="lockUntil", type="datetime", nullable=true)
     */
    protected $lockUntil;

    /**
     * @var string
     *
     * @ORM\Column(name="circles_field", type="string", length=100, nullable=true)
     */
    protected $circlesField;

    /**
     * @var string
     *
     * @ORM\Column(name="business_id_field", type="string", length=100, nullable=true)
     */
    protected $businessIdField;

    /**
     * @var bool
     *
     * @ORM\Column(name="deleted", type="boolean")
     */
    protected $deleted;

    /**
     * @var bool
     *
     * @ORM\Column(name="ask_for_ouuid", type="boolean")
     */
    protected $askForOuuid;

    /**
     * @var bool
     *
     * @ORM\Column(name="dirty", type="boolean")
     */
    protected $dirty;

    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    protected $color;

    /**
     * @ORM\OneToOne(targetEntity="FieldType", cascade={"persist"})
     * @ORM\JoinColumn(name="field_types_id", referencedColumnName="id")
     *
     * @var ?FieldType
     */
    protected $fieldType;

    /**
     * @var string
     *
     * @ORM\Column(name="labelField", type="string", length=100, nullable=true)
     */
    protected $labelField;

    /**
     * @var string
     *
     * @ORM\Column(name="color_field", type="string", length=100, nullable=true)
     */
    protected $colorField;

    /**
     * @var string
     *
     * @ORM\Column(name="userField", type="string", length=100, nullable=true)
     */
    protected $userField;

    /**
     * @var string
     *
     * @ORM\Column(name="dateField", type="string", length=100, nullable=true)
     */
    protected $dateField;

    /**
     * @var string
     *
     * @ORM\Column(name="startDateField", type="string", length=100, nullable=true)
     */
    protected $startDateField;

    /**
     * @var string
     *
     * @ORM\Column(name="endDateField", type="string", length=100, nullable=true)
     */
    protected $endDateField;

    /**
     * @var string
     *
     * @ORM\Column(name="locationField", type="string", length=100, nullable=true)
     */
    protected $locationField;

    /**
     * @var string
     *
     * @ORM\Column(name="referer_field_name", type="string", length=100, nullable=true)
     */
    protected $refererFieldName;

    /**
     * @var string
     *
     * @ORM\Column(name="category_field", type="string", length=100, nullable=true)
     */
    protected $categoryField;

    /**
     * @var string
     *
     * @ORM\Column(name="ouuidField", type="string", length=100, nullable=true)
     */
    protected $ouuidField;

    /**
     * @var string
     *
     * @ORM\Column(name="imageField", type="string", length=100, nullable=true)
     */
    protected $imageField;

    /**
     * @var string
     *
     * @ORM\Column(name="videoField", type="string", length=100, nullable=true)
     */
    protected $videoField;

    /**
     * @var string
     *
     * @ORM\Column(name="email_field", type="string", length=100, nullable=true)
     */
    protected $emailField;

    /**
     * @var string
     *
     * @ORM\Column(name="asset_field", type="string", length=100, nullable=true)
     */
    protected $assetField;

    /**
     * @var string
     *
     * @ORM\Column(name="order_field", type="string", length=100, nullable=true)
     */
    protected $orderField;

    /**
     * @var string
     *
     * @ORM\Column(name="sort_by", type="string", length=100, nullable=true)
     */
    protected $sortBy;

    /**
     * @var string
     *
     * @ORM\Column(name="sort_order", type="string", length=4, nullable=true, options={"default" : "asc"})
     */
    protected $sortOrder;

    /**
     * @var string
     *
     * @ORM\Column(name="create_role", type="string", length=100, nullable=true)
     */
    protected $createRole;

    /**
     * @var string
     *
     * @ORM\Column(name="edit_role", type="string", length=100, nullable=true)
     */
    protected $editRole;

    /**
     * @ORM\Column(name="view_role", type="string", length=100, nullable=true)
     * @ORM\OrderBy({"orderKey" = "ASC"})
     */
    protected ?string $viewRole;

    /**
     * @var string
     *
     * @ORM\Column(name="publish_role", type="string", length=100, nullable=true)
     */
    protected $publishRole;

    /**
     * @ORM\Column(name="delete_role", type="string", length=100, nullable=true)
     */
    protected ?string $deleteRole = null;

    /**
     * @var string
     *
     * @ORM\Column(name="trash_role", type="string", length=100, nullable=true)
     */
    protected $trashRole;

    /**
     * @ORM\Column(name="owner_role", type="string", length=100, nullable=true)
     */
    protected ?string $ownerRole = null;

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected $orderKey;

    /**
     * @var bool
     *
     * @ORM\Column(name="rootContentType", type="boolean")
     */
    protected $rootContentType;

    /**
     * @var bool
     *
     * @ORM\Column(name="edit_twig_with_wysiwyg", type="boolean")
     */
    protected $editTwigWithWysiwyg;

    /**
     * @var bool
     *
     * @ORM\Column(name="web_content", type="boolean", options={"default" : 1})
     */
    protected $webContent;

    /**
     * @var bool
     *
     * @ORM\Column(name="auto_publish", type="boolean", options={"default" : 0})
     */
    protected $autoPublish;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean")
     */
    protected $active;

    /**
     * @var Environment|null
     * @ORM\ManyToOne(targetEntity="Environment", inversedBy="contentTypesHavingThisAsDefault")
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    protected $environment;

    /**
     * @ORM\OneToMany(targetEntity="Template", mappedBy="contentType", cascade={"persist", "remove"})
     * @ORM\OrderBy({"orderKey" = "ASC"})
     *
     * @var ArrayCollection<int, Template>
     */
    protected $templates;

    /**
     * @ORM\OneToMany(targetEntity="View", mappedBy="contentType", cascade={"persist", "remove"})
     * @ORM\OrderBy({"orderKey" = "ASC"})
     *
     * @var ArrayCollection<int, View>
     */
    protected $views;

    /**
     * @var string
     * @ORM\Column(name="default_value", type="text", nullable=true)
     */
    public $defaultValue;

    /**
     * @var string
     *
     * @ORM\Column(name="translationField", type="string", length=100, nullable=true)
     */
    protected $translationField;

    /**
     * @var string
     *
     * @ORM\Column(name="localeField", type="string", length=100, nullable=true)
     */
    protected $localeField;

    /**
     * @var string
     *
     * @ORM\Column(name="searchLinkDisplayRole", type="string", options={"default" : "ROLE_USER"})
     */
    protected $searchLinkDisplayRole = 'ROLE_USER';

    /**
     * @var string
     *
     * @ORM\Column(name="createLinkDisplayRole", type="string", options={"default" : "ROLE_USER"})
     */
    protected $createLinkDisplayRole = 'ROLE_USER';

    /**
     * @var string[]
     *
     * @ORM\Column(name="version_tags", type="json_array", nullable=true)
     */
    protected $versionTags = [];

    /**
     * @var string|null
     *
     * @ORM\Column(name="version_date_from_field", type="string", length=100, nullable=true)
     */
    protected $versionDateFromField;

    /**
     * @var string|null
     *
     * @ORM\Column(name="version_date_to_field", type="string", length=100, nullable=true)
     */
    protected $versionDateToField;

    public function __construct()
    {
        $this->templates = new ArrayCollection();
        $this->views = new ArrayCollection();

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
     * @return ContentType
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
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
     * @return ContentType
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
     * Set name.
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set icon.
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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Set description.
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
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set lockBy.
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
     * Get lockBy.
     *
     * @return string
     */
    public function getLockBy()
    {
        return $this->lockBy;
    }

    /**
     * Set lockUntil.
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
     * Get lockUntil.
     *
     * @return \DateTime
     */
    public function getLockUntil()
    {
        return $this->lockUntil;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return ContentType
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
     * Set color.
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
     * Get color.
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set labelField.
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

    public function hasLabelField(): bool
    {
        return null !== $this->labelField && \strlen($this->labelField) > 0;
    }

    public function giveLabelField(): string
    {
        return $this->labelField;
    }

    public function getLabelField(): ?string
    {
        return $this->labelField;
    }

    /**
     * Set dateField.
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
     * Get dateField.
     *
     * @return string
     */
    public function getDateField()
    {
        return $this->dateField;
    }

    /**
     * Set endDateField.
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
     * Get endDateField.
     *
     * @return string
     */
    public function getEndDateField()
    {
        return $this->endDateField;
    }

    /**
     * Set locationField.
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
     * Get locationField.
     *
     * @return string
     */
    public function getLocationField()
    {
        return $this->locationField;
    }

    /**
     * Set ouuidField.
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
     * Get ouuidField.
     *
     * @return string
     */
    public function getOuuidField()
    {
        return $this->ouuidField;
    }

    /**
     * Set imageField.
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
     * Get imageField.
     *
     * @return string
     */
    public function getImageField()
    {
        return $this->imageField;
    }

    /**
     * Set videoField.
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
     * Get videoField.
     *
     * @return string
     */
    public function getVideoField()
    {
        return $this->videoField;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return ContentType
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
     * Set rootContentType.
     *
     * @param bool $rootContentType
     *
     * @return ContentType
     */
    public function setRootContentType($rootContentType)
    {
        $this->rootContentType = $rootContentType;

        return $this;
    }

    /**
     * Get rootContentType.
     *
     * @return bool
     */
    public function getRootContentType()
    {
        return $this->rootContentType;
    }

    /**
     * Set pluralName.
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
     * Get pluralName.
     *
     * @return string
     */
    public function getPluralName()
    {
        return $this->pluralName;
    }

    /**
     * Set startDateField.
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
     * Get startDateField.
     *
     * @return string
     */
    public function getStartDateField()
    {
        return $this->startDateField;
    }

    /**
     * Set userField.
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
     * Get userField.
     *
     * @return string
     */
    public function getUserField()
    {
        return $this->userField;
    }

    /**
     * Set indexTwig.
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
     * Get indexTwig.
     *
     * @return string
     */
    public function getIndexTwig()
    {
        return $this->indexTwig;
    }

    /**
     * Set extra.
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
     * Get extra.
     *
     * @return string
     */
    public function getExtra()
    {
        return $this->extra;
    }

    public function hasFieldType(): bool
    {
        return null !== $this->fieldType;
    }

    public function getFieldType(): FieldType
    {
        if (null === $this->fieldType) {
            throw new \RuntimeException('Field type is unset!');
        }

        return $this->fieldType;
    }

    /**
     * @return string[]
     */
    public function getClearOnCopyProperties(): array
    {
        $clearPropertyNames = [];

        foreach ($this->getFieldType()->loopChildren() as $child) {
            $extraOptions = $child->getExtraOptions();

            $clearOnCopy = true === ($extraOptions['clear_on_copy'] ?? null);

            if ($clearOnCopy) {
                $clearPropertyNames[] = $child->getName();
            }
        }

        return $clearPropertyNames;
    }

    /**
     * Set active.
     *
     * @param bool $active
     *
     * @return ContentType
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active.
     *
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set fieldType.
     *
     * @return ContentType
     */
    public function setFieldType(FieldType $fieldType)
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    /**
     * Unset fieldType.
     *
     * @return ContentType
     */
    public function unsetFieldType()
    {
        $this->fieldType = null;

        return $this;
    }

    /**
     * Set environment.
     *
     * @param Environment $environment
     *
     * @return ContentType
     */
    public function setEnvironment(Environment $environment = null)
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get environment.
     *
     * @return Environment|null
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    public function giveEnvironment(): Environment
    {
        if (null === $this->environment) {
            throw new \RuntimeException('Environment not found!');
        }

        return $this->environment;
    }

    /**
     * Set categoryField.
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

    public function hasCategoryField(): bool
    {
        return null !== $this->categoryField && \strlen($this->categoryField) > 0;
    }

    public function giveCategoryField(): string
    {
        return $this->categoryField;
    }

    /**
     * Get categoryField.
     *
     * @return string
     */
    public function getCategoryField()
    {
        return $this->categoryField;
    }

    /**
     * Set dirty.
     *
     * @param bool $dirty
     *
     * @return ContentType
     */
    public function setDirty($dirty)
    {
        $this->dirty = $dirty;

        return $this;
    }

    public function getDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * Set editTwigWithWysiwyg.
     *
     * @param bool $editTwigWithWysiwyg
     *
     * @return ContentType
     */
    public function setEditTwigWithWysiwyg($editTwigWithWysiwyg)
    {
        $this->editTwigWithWysiwyg = $editTwigWithWysiwyg;

        return $this;
    }

    /**
     * Get editTwigWithWysiwyg.
     *
     * @return bool
     */
    public function getEditTwigWithWysiwyg()
    {
        return $this->editTwigWithWysiwyg;
    }

    /**
     * Set webContent.
     *
     * @param bool $webContent
     *
     * @return ContentType
     */
    public function setWebContent($webContent)
    {
        $this->webContent = $webContent;

        return $this;
    }

    /**
     * Get webContent.
     *
     * @return bool
     */
    public function getWebContent()
    {
        return $this->webContent;
    }

    /**
     * @return string[]
     */
    public function getRenderingSourceFields(): array
    {
        $sourceFields = [];
        if ($this->hasLabelField()) {
            $sourceFields[] = $this->giveLabelField();
        }
        if ($this->hasColorField()) {
            $sourceFields[] = $this->giveColorField();
        }
        if ($this->hasCategoryField()) {
            $sourceFields[] = $this->giveCategoryField();
        }

        return $sourceFields;
    }

    /**
     * Add template.
     *
     * @return ContentType
     */
    public function addTemplate(Template $template)
    {
        $this->templates[] = $template;

        return $this;
    }

    /**
     * Remove template.
     */
    public function removeTemplate(Template $template)
    {
        $this->templates->removeElement($template);
    }

    /**
     * Get templates.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTemplates()
    {
        return $this->templates;
    }

    /**
     * Add view.
     *
     * @return ContentType
     */
    public function addView(View $view)
    {
        $this->views[] = $view;

        return $this;
    }

    /**
     * Remove view.
     */
    public function removeView(View $view)
    {
        $this->views->removeElement($view);
    }

    /**
     * @return ArrayCollection<int, View>
     */
    public function getViews()
    {
        return $this->views;
    }

    public function getFirstViewByType(string $type): ?View
    {
        $view = $this->views->filter(fn (View $view) => $view->getType() == $type)->first();

        return $view instanceof View ? $view : null;
    }

    /**
     * Set askForOuuid.
     *
     * @param bool $askForOuuid
     *
     * @return ContentType
     */
    public function setAskForOuuid($askForOuuid)
    {
        $this->askForOuuid = $askForOuuid;

        return $this;
    }

    /**
     * Get askForOuuid.
     *
     * @return bool
     */
    public function getAskForOuuid()
    {
        return $this->askForOuuid;
    }

    /**
     * Set colorField.
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

    public function hasColorField(): bool
    {
        return null !== $this->colorField && \strlen($this->colorField) > 0;
    }

    public function giveColorField(): string
    {
        return $this->colorField;
    }

    public function getColorField(): ?string
    {
        return $this->colorField;
    }

    /**
     * Set circlesField.
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

    public function getCirclesField(): ?string
    {
        return $this->circlesField;
    }

    /**
     * Set emailField.
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
     * Get emailField.
     *
     * @return string
     */
    public function getEmailField()
    {
        return $this->emailField;
    }

    /**
     * Set createRole.
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
     * Get createRole.
     *
     * @return string
     */
    public function getCreateRole()
    {
        return $this->createRole;
    }

    /**
     * Set editRole.
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
     * Get editRole.
     *
     * @return string
     */
    public function getEditRole()
    {
        return $this->editRole;
    }

    /**
     * Set assetField.
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

    public function hasAssetField(): bool
    {
        return null !== $this->assetField && \strlen($this->assetField) > 0;
    }

    /**
     * Get assetField.
     *
     * @return string
     */
    public function getAssetField()
    {
        return $this->assetField;
    }

    /**
     * Set orderField.
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
     * Get orderField.
     *
     * @return string
     */
    public function getOrderField()
    {
        return $this->orderField;
    }

    /**
     * Set sortBy.
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
     * Get sortBy.
     *
     * @return string
     */
    public function getSortBy()
    {
        return $this->sortBy;
    }

    /**
     * Set refererFieldName.
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
     * Get refererFieldName.
     *
     * @return string
     */
    public function getRefererFieldName()
    {
        return $this->refererFieldName;
    }

    /**
     * Set viewRole.
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

    public function getViewRole(): ?string
    {
        return $this->viewRole;
    }

    /**
     * Set publishRole.
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
     * Get publishRole.
     *
     * @return string
     */
    public function getPublishRole()
    {
        return $this->publishRole;
    }

    public function hasDeleteRole(): bool
    {
        return null !== $this->deleteRole;
    }

    public function getDeleteRole(): ?string
    {
        return $this->deleteRole;
    }

    public function setDeleteRole(?string $deleteRole): ContentType
    {
        $this->deleteRole = $deleteRole;

        return $this;
    }

    /**
     * Set trashRole.
     *
     * @param string $trashRole
     *
     * @return ContentType
     */
    public function setTrashRole($trashRole)
    {
        $this->trashRole = $trashRole;

        return $this;
    }

    /**
     * Get trashRole.
     *
     * @return string
     */
    public function getTrashRole()
    {
        return $this->trashRole;
    }

    /**
     * Set singularName.
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
     * Get singularName.
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
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string $defaultValue
     *
     * @return ContentType
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function isAutoPublish(): bool
    {
        return $this->autoPublish;
    }

    public function setAutoPublish(bool $autoPublish): ContentType
    {
        $this->autoPublish = $autoPublish;

        return $this;
    }

    public function reset(int $nextOrderKey): void
    {
        $this->setActive(false);
        $this->setDirty(true);
        $this->getFieldType()->updateAncestorReferences($this, null);
        $this->setOrderKey($nextOrderKey);
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
        $this->getFieldType()->removeCircularReference();

        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->removeProperty('environment');
        $json->handlePersistentCollections('templates', 'views');

        return $json;
    }

    /**
     * @param mixed $value
     */
    protected function deserializeProperty(string $name, $value): void
    {
        switch ($name) {
            case 'templates':
                /** @var Template $template */
                foreach ($this->deserializeArray($value) as $template) {
                    $this->addTemplate($template);
                    $template->setContentType($this);
                }
                break;
            case 'views':
                /** @var View $view */
                foreach ($this->deserializeArray($value) as $view) {
                    $this->addView($view);
                    $view->setContentType($this);
                }
                break;
            default:
                parent::deserializeProperty($name, $value);
        }
    }

    public function getBusinessIdField(): ?string
    {
        return $this->businessIdField;
    }

    public function setBusinessIdField(string $businessIdField): ContentType
    {
        $this->businessIdField = $businessIdField;

        return $this;
    }

    public function setTranslationField(string $translationField): ContentType
    {
        $this->translationField = $translationField;

        return $this;
    }

    public function getTranslationField(): ?string
    {
        return $this->translationField;
    }

    public function setLocaleField(?string $localeField): ContentType
    {
        $this->localeField = $localeField;

        return $this;
    }

    public function getLocaleField(): ?string
    {
        return $this->localeField;
    }

    public function setSearchLinkDisplayRole(string $searchLinkDisplayRole): ContentType
    {
        $this->searchLinkDisplayRole = $searchLinkDisplayRole;

        return $this;
    }

    public function getSearchLinkDisplayRole(): string
    {
        return $this->searchLinkDisplayRole;
    }

    public function setCreateLinkDisplayRole(string $createLinkDisplayRole): ContentType
    {
        $this->createLinkDisplayRole = $createLinkDisplayRole;

        return $this;
    }

    public function getCreateLinkDisplayRole(): string
    {
        return $this->createLinkDisplayRole;
    }

    public function hasVersionTags(): bool
    {
        return \count($this->versionTags) > 0;
    }

    /**
     * @return string[]
     */
    public function getVersionTags(): array
    {
        return $this->versionTags;
    }

    /**
     * @param string[] $versionTags
     */
    public function setVersionTags(array $versionTags): void
    {
        $this->versionTags = $versionTags;
    }

    public function getVersionDateFromField(): ?string
    {
        return $this->versionDateFromField;
    }

    public function setVersionDateFromField(?string $versionDateFromField): void
    {
        $this->versionDateFromField = $versionDateFromField;
    }

    public function getVersionDateToField(): ?string
    {
        return $this->versionDateToField;
    }

    public function setVersionDateToField(?string $versionDateToField): void
    {
        $this->versionDateToField = $versionDateToField;
    }

    /**
     * @return string[]
     */
    public function getDisabledDataFields(): array
    {
        return \array_filter([
            $this->getVersionDateFromField(),
            $this->getVersionDateToField(),
        ]);
    }

    public function hasOwnerRole(): bool
    {
        return null !== $this->ownerRole;
    }

    public function giveOwnerRole(): string
    {
        $ownerRole = $this->ownerRole;

        if (null === $ownerRole) {
            throw new \RuntimeException('No owner role specified');
        }

        return $ownerRole;
    }

    public function getOwnerRole(): ?string
    {
        return $this->ownerRole;
    }

    public function setOwnerRole(?string $ownerRole): void
    {
        $this->ownerRole = $ownerRole;
    }
}
