<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * DataField.
 *
 * @ORM\Table(name="template")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\TemplateRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Template extends JsonDeserializer implements \JsonSerializable, EntityInterface
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
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected string $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @var string
     *
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     */
    protected $icon;

    /**
     * @var string
     *
     * @ORM\Column(name="body", type="text", nullable=true)
     */
    protected $body;

    /**
     * @var string
     *
     * @ORM\Column(name="header", type="text", nullable=true)
     */
    protected $header;

    /**
     * @var bool
     *
     * @ORM\Column(name="edit_with_wysiwyg", type="boolean")
     */
    protected $editWithWysiwyg;

    /** @var string
     *
     * @ORM\Column(name="render_option", type="string")
     */
    protected $renderOption;

    /**
     * @var int
     *
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected $orderKey;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType", inversedBy="templates")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    protected $contentType;

    /**
     * @var bool
     *
     * @ORM\Column(name="accumulate_in_one_file", type="boolean")
     */
    protected $accumulateInOneFile;

    /** @var string

     * @var bool
     *
     * @ORM\Column(name="preview", type="boolean")
     */
    protected $preview;

    /** @var string
     *
     * @ORM\Column(name="mime_type", type="string", nullable=true)
     */
    protected $mimeType;

    /** @var string
     *
     * @ORM\Column(name="filename", type="text", nullable=true)
     */
    protected $filename;

    /** @var string
     *
     * @ORM\Column(name="extension", type="string", nullable=true)
     */
    protected $extension;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean")
     */
    protected $active;

    /**
     * @var string
     *
     * @ORM\Column(name="role", type="string")
     */
    protected $role;

    /**
     * @ORM\ManyToMany(targetEntity="Environment", cascade={"persist"})
     * @ORM\JoinTable(name="environment_template",
     *      joinColumns={@ORM\JoinColumn(name="template_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="environment_id", referencedColumnName="id")}
     *      )
     */
    protected $environments;

    /** @var string
     *
     * @ORM\Column(name="role_to", type="string")
     */
    protected $roleTo;

    /** @var string
     *
     * @ORM\Column(name="role_cc", type="string")
     */
    protected $roleCc;

    /**
     * @var array
     *
     * @ORM\Column(name="circles_to", type="json_array", nullable=true)
     */
    protected $circlesTo;

    /**
     * @var string
     *
     * @ORM\Column(name="response_template", type="text", nullable=true)
     */
    protected $responseTemplate;

    /** @var string
     *
     * @ORM\Column(name="email_content_type", type="string", nullable=true)
     */
    protected $emailContentType;

    /** @var string
     *
     * @ORM\Column(name="allow_origin", type="string", nullable=true)
     */
    protected $allowOrigin;

    /** @var string
     *
     * @ORM\Column(name="disposition", type="string", length=20, nullable=true)
     */
    protected $disposition;

    /**
     * @var string
     *
     * @ORM\Column(name="orientation", type="string", length=20, nullable=true)
     */
    protected $orientation;

    /**
     * @var string
     *
     * @ORM\Column(name="size", type="string", length=20, nullable=true)
     */
    protected $size;

    /**
     * @var bool
     *
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    protected $public;

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
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
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
     * @return Template
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
     * @return Template
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
     * @return Template
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
     * @return Template
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get icon.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Set body.
     *
     * @param string $body
     *
     * @return Template
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get body.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set editWithWysiwyg.
     *
     * @param bool $editWithWysiwyg
     *
     * @return Template
     */
    public function setEditWithWysiwyg($editWithWysiwyg)
    {
        $this->editWithWysiwyg = $editWithWysiwyg;

        return $this;
    }

    /**
     * Get editWithWysiwyg.
     *
     * @return bool
     */
    public function getEditWithWysiwyg()
    {
        return $this->editWithWysiwyg;
    }

    /**
     * Set renderOption.
     *
     * @param string $renderOption
     *
     * @return Template
     */
    public function setRenderOption($renderOption)
    {
        $this->renderOption = $renderOption;

        return $this;
    }

    /**
     * Get renderOption.
     *
     * @return string
     */
    public function getRenderOption()
    {
        return $this->renderOption;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return Template
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
     * Set accumulateInOneFile.
     *
     * @param bool $accumulateInOneFile
     *
     * @return Template
     */
    public function setAccumulateInOneFile($accumulateInOneFile)
    {
        $this->accumulateInOneFile = $accumulateInOneFile;

        return $this;
    }

    /**
     * Get accumulateInOneFile.
     *
     * @return bool
     */
    public function getAccumulateInOneFile()
    {
        return $this->accumulateInOneFile;
    }

    /**
     * Set preview.
     *
     * @param bool $preview
     *
     * @return Template
     */
    public function setPreview($preview)
    {
        $this->preview = $preview;

        return $this;
    }

    /**
     * Get preview.
     *
     * @return bool
     */
    public function getPreview()
    {
        return $this->preview;
    }

    /**
     * Set mimeType.
     *
     * @param string $mimeType
     *
     * @return Template
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Get mimeType.
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Set filename.
     *
     * @param string $filename
     *
     * @return Template
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Set extension.
     *
     * @param string $extension
     *
     * @return Template
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * Get extension.
     *
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * Set active.
     *
     * @param bool $active
     *
     * @return Template
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
     * Set role.
     *
     * @param string $role
     *
     * @return Template
     */
    public function setRole($role)
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Get role.
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Set roleTo.
     *
     * @param string $roleTo
     *
     * @return Template
     */
    public function setRoleTo($roleTo)
    {
        $this->roleTo = $roleTo;

        return $this;
    }

    /**
     * Get roleTo.
     *
     * @return string
     */
    public function getRoleTo()
    {
        return $this->roleTo;
    }

    /**
     * Set roleCc.
     *
     * @param string $roleCc
     *
     * @return Template
     */
    public function setRoleCc($roleCc)
    {
        $this->roleCc = $roleCc;

        return $this;
    }

    /**
     * Get roleCc.
     *
     * @return string
     */
    public function getRoleCc()
    {
        return $this->roleCc;
    }

    /**
     * Set circlesTo.
     *
     * @param array $circlesTo
     *
     * @return Template
     */
    public function setCirclesTo($circlesTo)
    {
        $this->circlesTo = $circlesTo;

        return $this;
    }

    /**
     * Get circlesTo.
     *
     * @return array
     */
    public function getCirclesTo()
    {
        return $this->circlesTo;
    }

    /**
     * Set responseTemplate.
     *
     * @param string $responseTemplate
     *
     * @return Template
     */
    public function setResponseTemplate($responseTemplate)
    {
        $this->responseTemplate = $responseTemplate;

        return $this;
    }

    /**
     * Get responseTemplate.
     *
     * @return string
     */
    public function getResponseTemplate()
    {
        return $this->responseTemplate;
    }

    /**
     * Set contentType.
     *
     * @param \EMS\CoreBundle\Entity\ContentType $contentType
     *
     * @return Template
     */
    public function setContentType(ContentType $contentType = null)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get contentType.
     *
     * @return \EMS\CoreBundle\Entity\ContentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    public function giveContentType(): ContentType
    {
        if (null === $this->contentType) {
            throw new \RuntimeException('Not found contentType');
        }

        return $this->contentType;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->environments = new \Doctrine\Common\Collections\ArrayCollection();
        $this->public = false;
    }

    /**
     * ToString.
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Add environment.
     *
     * @return Template
     */
    public function addEnvironment(Environment $environment)
    {
        $this->environments[] = $environment;

        return $this;
    }

    /**
     * Remove environment.
     */
    public function removeEnvironment(Environment $environment)
    {
        $this->environments->removeElement($environment);
    }

    /**
     * Get environments.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getEnvironments()
    {
        return $this->environments->toArray();
    }

    /**
     * is Environment Exist.
     *
     * Use in twig object-views-button.html.twig
     *
     * @return bool
     */
    public function isEnvironmentExist($name)
    {
        foreach ($this->environments as $environment) {
            if ($environment->getname() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set emailContentType.
     *
     * @param string $emailContentType
     *
     * @return Template
     */
    public function setEmailContentType($emailContentType)
    {
        $this->emailContentType = $emailContentType;

        return $this;
    }

    /**
     * Get emailContentType.
     *
     * @return string
     */
    public function getEmailContentType()
    {
        return $this->emailContentType;
    }

    /**
     * Set header.
     *
     * @param string $header
     *
     * @return Template
     */
    public function setHeader($header)
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Get header.
     *
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return string
     */
    public function getOrientation()
    {
        return $this->orientation;
    }

    /**
     * @param string $orientation
     *
     * @return Template
     */
    public function setOrientation($orientation)
    {
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * @return string
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param string $size
     *
     * @return Template
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): Template
    {
        $this->public = $public;

        return $this;
    }

    /**
     * @return string
     */
    public function getAllowOrigin()
    {
        return $this->allowOrigin;
    }

    /**
     * @param string $allowOrigin
     *
     * @return Template
     */
    public function setAllowOrigin($allowOrigin)
    {
        $this->allowOrigin = $allowOrigin;

        return $this;
    }

    /**
     * @return string
     */
    public function getDisposition()
    {
        return $this->disposition;
    }

    /**
     * @param string $disposition
     *
     * @return Template
     */
    public function setDisposition($disposition)
    {
        $this->disposition = $disposition;

        return $this;
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
        $json->removeProperty('contentType');
        $json->removeProperty('environments');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }

    /**
     * @param mixed $value
     */
    protected function deserializeProperty(string $name, $value): void
    {
        switch ($name) {
            case 'environments':
                foreach ($this->deserializeArray($value) as $environment) {
                    $this->addEnvironment($environment);
                }
                break;
            default:
                parent::deserializeProperty($name, $value);
        }
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }


}
