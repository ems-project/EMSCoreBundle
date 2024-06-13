<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\ContentType\ContentTypeFields;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\ContentType\ContentTypeSettings;
use EMS\CoreBundle\Core\ContentType\Version\VersionFields;
use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;
use EMS\CoreBundle\Roles;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;

/**
 * @ORM\Table(name="content_type")
 *
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\ContentTypeRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class ContentType extends JsonDeserializer implements \JsonSerializable, EntityInterface, \Stringable
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="bigint")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected string $id;

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
     * @ORM\Column(name="deleted", type="boolean")
     */
    protected bool $deleted = false;

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
    protected $dirty = true;

    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    protected $color;

    /**
     * @ORM\OneToOne(targetEntity="FieldType", cascade={"persist"})
     *
     * @ORM\JoinColumn(name="field_types_id", referencedColumnName="id")
     *
     * @var ?FieldType
     */
    protected $fieldType;

    /**
     * @var string
     *
     * @ORM\Column(name="referer_field_name", type="string", length=100, nullable=true)
     */
    protected $refererFieldName;

    /**
     * @ORM\Column(name="sort_order", type="string", length=4, nullable=true, options={"default" : "asc"})
     */
    protected ?string $sortOrder = null;

    /**
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected int $orderKey = 0;

    /**
     * @ORM\Column(name="rootContentType", type="boolean")
     */
    protected bool $rootContentType = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="edit_twig_with_wysiwyg", type="boolean")
     */
    protected $editTwigWithWysiwyg = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="web_content", type="boolean", options={"default" : 1})
     */
    protected $webContent = true;

    /**
     * @var bool
     *
     * @ORM\Column(name="auto_publish", type="boolean", options={"default" : 0})
     */
    protected $autoPublish = false;

    /**
     * @ORM\Column(name="active", type="boolean")
     */
    protected bool $active = false;

    /**
     * @var Environment|null
     *
     * @ORM\ManyToOne(targetEntity="Environment", inversedBy="contentTypesHavingThisAsDefault")
     *
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    protected $environment;

    /**
     * @ORM\OneToMany(targetEntity="Template", mappedBy="contentType", cascade={"persist", "remove"})
     *
     * @ORM\OrderBy({"orderKey" = "ASC"})
     *
     * @var Collection<int, Template>
     */
    protected $templates;

    /**
     * @ORM\OneToMany(targetEntity="View", mappedBy="contentType", cascade={"persist", "remove"})
     *
     * @ORM\OrderBy({"orderKey" = "ASC"})
     *
     * @var Collection<int, View>
     */
    protected $views;

    /**
     * @var string
     *
     * @ORM\Column(name="default_value", type="text", nullable=true)
     */
    public $defaultValue;

    /**
     * @var ?string[]
     *
     * @ORM\Column(name="version_tags", type="json", nullable=true)
     */
    protected ?array $versionTags = [];

    /**
     * @var ?array<string, bool>
     *
     * @ORM\Column(name="version_options", type="json", nullable=true)
     */
    protected ?array $versionOptions = [];

    /**
     * @var ?array<string, ?string>
     *
     * @ORM\Column(name="version_fields", type="json", nullable=true)
     */
    protected ?array $versionFields = [];

    /**
     * @var array<string, string>
     *
     * @ORM\Column(name="roles", type="json", nullable=true)
     */
    protected array $roles = [];

    /**
     * @var array<string, ?string>
     *
     * @ORM\Column(name="fields", type="json", nullable=true)
     */
    protected array $fields = [];

    /**
     * @var array<string, bool|string[]>
     *
     * @ORM\Column(name="settings", type="json", nullable=true)
     */
    protected ?array $settings = null;

    public function __construct()
    {
        $this->templates = new ArrayCollection();
        $this->views = new ArrayCollection();

        $fieldType = new FieldType();
        $fieldType->setName('source');
        $fieldType->setType(ContainerFieldType::class);
        $fieldType->setContentType($this);
        $this->setFieldType($fieldType);
        $this->setAskForOuuid(true);

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return (int) $this->id;
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

    public function hasLabelField(): bool
    {
        return null !== $this->field(ContentTypeFields::LABEL);
    }

    public function giveLabelField(): string
    {
        if (null === $labelField = $this->field(ContentTypeFields::LABEL)) {
            throw new \RuntimeException('Label field not defined');
        }

        return $labelField;
    }

    public function getLabelField(): ?string
    {
        return $this->field(ContentTypeFields::LABEL);
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

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function isActive(): bool
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

    public function hasCategoryField(): bool
    {
        return null !== $this->field(ContentTypeFields::CATEGORY);
    }

    public function giveCategoryField(): string
    {
        if (null === $categoryField = $this->field(ContentTypeFields::CATEGORY)) {
            throw new \RuntimeException('Category field not defined');
        }

        return $categoryField;
    }

    public function getCategoryField(): ?string
    {
        return $this->field(ContentTypeFields::CATEGORY);
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
        if ($this->field(ContentTypeFields::TOOLTIP)) {
            $sourceFields[] = $this->field(ContentTypeFields::TOOLTIP);
        }

        return $sourceFields;
    }

    public function getActionByName(string $actionName): ?Template
    {
        foreach ($this->getTemplates() as $item) {
            if ($actionName === $item->getName()) {
                return $item;
            }
        }
        if (\is_numeric($actionName)) {
            \trigger_error('Using template ID is deprecated, use the action name instead', E_USER_DEPRECATED);

            return $this->getActionById(\intval($actionName));
        }

        return null;
    }

    public function getActionById(int $actionId): Template
    {
        foreach ($this->getTemplates() as $item) {
            if ($actionId === $item->getId()) {
                return $item;
            }
        }
        throw new \RuntimeException(\sprintf('Action id %d not found for content type %s', $actionId, $this->getSingularName()));
    }

    public function addTemplate(Template $template): void
    {
        if ($this->templates->contains($template)) {
            $this->templates->removeElement($template);
        }

        $this->templates->add($template);
    }

    public function removeTemplate(Template $template): void
    {
        $this->templates->removeElement($template);
    }

    /**
     * @return Collection<int, Template>
     */
    public function getTemplates(): Collection
    {
        return $this->templates;
    }

    public function addView(View $view): void
    {
        if ($this->views->contains($view)) {
            $this->views->removeElement($view);
        }

        $this->views->add($view);
    }

    public function removeView(View $view): void
    {
        $this->views->removeElement($view);
    }

    /**
     * @return Collection<int, View>
     */
    public function getViews(): Collection
    {
        return $this->views;
    }

    public function getViewByName(string $name): ?View
    {
        $view = $this->views->filter(fn (View $view) => $view->getName() === $name)->first();

        return $view instanceof View ? $view : null;
    }

    public function getFirstViewByType(string $type): ?View
    {
        $view = $this->views->filter(fn (View $view) => $view->getType() === $type)->first();

        return $view instanceof View ? $view : null;
    }

    public function getViewByDefinition(ViewDefinition $viewDefinition): ?View
    {
        $view = $this->views->filter(fn (View $view) => $view->getDefinition() === $viewDefinition->value)->first();

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

    public function hasColorField(): bool
    {
        return null !== $this->field(ContentTypeFields::COLOR);
    }

    public function giveColorField(): string
    {
        if (null === $colorField = $this->field(ContentTypeFields::COLOR)) {
            throw new \RuntimeException('Color field not defined');
        }

        return $colorField;
    }

    public function getColorField(): ?string
    {
        return $this->field(ContentTypeFields::COLOR);
    }

    public function getCirclesField(): ?string
    {
        return $this->field(ContentTypeFields::CIRCLES);
    }

    public function hasAssetField(): bool
    {
        return null !== $this->field(ContentTypeFields::ASSET);
    }

    public function getAssetField(): ?string
    {
        return $this->field(ContentTypeFields::ASSET);
    }

    public function getSortBy(): ?string
    {
        return $this->field(ContentTypeFields::SORT);
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
        if ($this->getOrderKey() < 1) {
            $this->setOrderKey($nextOrderKey);
        }
    }

    public function jsonSerialize(): JsonClass
    {
        $this->getFieldType()->removeCircularReference();

        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('environment');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('dirty');
        $json->removeProperty('active');
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
                foreach ($value as $item) {
                    $json = JsonClass::fromJsonString(Json::encode($item));

                    $name = $json->getProperty('name');
                    $currentAction = \is_string($name) ? $this->getActionByName($name) : null;

                    /** @var Template $template */
                    $template = $json->jsonDeserialize($currentAction);

                    $this->addTemplate($template);
                    $template->setContentType($this);
                }
                break;
            case 'views':
                foreach ($value as $item) {
                    $json = JsonClass::fromJsonString(Json::encode($item));

                    $name = $json->getProperty('name');
                    $currentView = \is_string($name) ? $this->getViewByName($name) : null;

                    /** @var View $view */
                    $view = $json->jsonDeserialize($currentView);

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
        return $this->field(ContentTypeFields::BUSINESS_ID);
    }

    public function hasVersionTags(): bool
    {
        return \count($this->versionTags ?? []) > 0;
    }

    /**
     * @return string[]
     */
    public function getVersionTags(): array
    {
        return $this->versionTags ?? [];
    }

    /**
     * @param ?string[] $versionTags
     */
    public function setVersionTags(?array $versionTags): void
    {
        $this->versionTags = $versionTags;
    }

    public function getVersionOptions(): VersionOptions
    {
        return new VersionOptions($this->versionOptions ?? []);
    }

    public function setVersionOptions(VersionOptions $versionOptions): void
    {
        $this->versionOptions = $versionOptions->getOptions();
    }

    public function versionField(string $field): ?string
    {
        return $this->getVersionFields()[$field] ?? null;
    }

    public function getVersionFields(): VersionFields
    {
        return new VersionFields($this->versionFields ?? []);
    }

    public function setVersionFields(VersionFields $versionFields): void
    {
        $this->versionFields = $versionFields->getFields();
    }

    public function getVersionDateFromField(): ?string
    {
        return $this->versionField(VersionFields::DATE_FROM);
    }

    public function getVersionDateToField(): ?string
    {
        return $this->versionField(VersionFields::DATE_TO);
    }

    public function hasVersionTagField(): bool
    {
        return null !== $this->versionField(VersionFields::VERSION_TAG);
    }

    public function getVersionTagField(): string
    {
        return Type::string($this->versionField(VersionFields::VERSION_TAG));
    }

    /**
     * @return string[]
     */
    public function getDisabledDataFields(): array
    {
        if ($this->getVersionOptions()[VersionOptions::DATES_READ_ONLY]) {
            return \array_filter([
                $this->getVersionDateFromField(),
                $this->getVersionDateToField(),
            ]);
        }

        return [];
    }

    public function role(string $role): string
    {
        return $this->getRoles()[$role] ?? Roles::NOT_DEFINED;
    }

    public function getRoles(): ContentTypeRoles
    {
        return new ContentTypeRoles($this->roles ?? []);
    }

    public function setRoles(ContentTypeRoles $roles): void
    {
        $this->roles = $roles->getRoles();
    }

    public function field(string $field): ?string
    {
        return $this->getFields()[$field] ?? null;
    }

    public function getFields(): ContentTypeFields
    {
        return new ContentTypeFields($this->fields ?? []);
    }

    public function setFields(ContentTypeFields $fields): void
    {
        $this->fields = $fields->getFields();
    }

    public function tasksEnabled(): bool
    {
        return $this->getSettings()->getSettingBool(ContentTypeSettings::TASKS_ENABLED);
    }

    public function hideRevisionSidebarEnabled(): bool
    {
        return $this->getSettings()->getSettingBool(ContentTypeSettings::HIDE_REVISION_SIDEBAR);
    }

    public function getSettings(): ContentTypeSettings
    {
        return new ContentTypeSettings($this->settings ?? []);
    }

    public function setSettings(ContentTypeSettings $settings): void
    {
        $this->settings = $settings->getSettings();
    }
}
