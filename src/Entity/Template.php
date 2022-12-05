<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Type;

/**
 * @ORM\Table(name="template")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\TemplateRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Template extends JsonDeserializer implements \JsonSerializable, EntityInterface, \Stringable
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected string $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label = '';

    /**
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     */
    protected ?string $icon = null;

    /**
     * @ORM\Column(name="body", type="text", nullable=true)
     */
    protected ?string $body = null;

    /**
     * @ORM\Column(name="header", type="text", nullable=true)
     */
    protected ?string $header = null;

    /**
     * @ORM\Column(name="edit_with_wysiwyg", type="boolean")
     */
    protected bool $editWithWysiwyg = false;

    /**
     * @ORM\Column(name="render_option", type="string")
     */
    protected string $renderOption = '';

    /**
     * @ORM\Column(name="orderKey", type="integer")
     */
    protected int $orderKey = 0;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType", inversedBy="templates")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    protected ?ContentType $contentType = null;

    /**
     * @ORM\Column(name="accumulate_in_one_file", type="boolean")
     */
    protected bool $accumulateInOneFile = false;

    /**
     * @ORM\Column(name="preview", type="boolean")
     */
    protected bool $preview = false;

    /**
     * @ORM\Column(name="mime_type", type="string", nullable=true)
     */
    protected ?string $mimeType = null;

    /**
     * @ORM\Column(name="filename", type="text", nullable=true)
     */
    protected ?string $filename = null;

    /**
     * @ORM\Column(name="extension", type="string", nullable=true)
     */
    protected ?string $extension = null;

    /**
     * @ORM\Column(name="active", type="boolean")
     */
    protected bool $active = false;

    /**
     * @ORM\Column(name="role", type="string")
     */
    protected string $role = 'not-defined';

    /**
     * @var Collection<int, Environment>
     *
     * @ORM\ManyToMany(targetEntity="Environment", cascade={"persist"})
     * @ORM\JoinTable(name="environment_template",
     *      joinColumns={@ORM\JoinColumn(name="template_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="environment_id", referencedColumnName="id")}
     *      )
     */
    protected Collection $environments;

    /**
     * @ORM\Column(name="role_to", type="string")
     */
    protected string $roleTo = 'not-defined';

    /**
     * @ORM\Column(name="role_cc", type="string")
     */
    protected string $roleCc = 'not-defined';

    /**
     * @var ?string[]
     *
     * @ORM\Column(name="circles_to", type="json", nullable=true)
     */
    protected ?array $circlesTo = null;

    /**
     * @ORM\Column(name="response_template", type="text", nullable=true)
     */
    protected ?string $responseTemplate = null;

    /**
     * @ORM\Column(name="email_content_type", type="string", nullable=true)
     */
    protected ?string $emailContentType = null;

    /**
     * @ORM\Column(name="allow_origin", type="string", nullable=true)
     */
    protected ?string $allowOrigin = null;

    /**
     * @ORM\Column(name="disposition", type="string", length=20, nullable=true)
     */
    protected ?string $disposition = null;

    /**
     * @ORM\Column(name="orientation", type="string", length=20, nullable=true)
     */
    protected ?string $orientation = null;

    /**
     * @ORM\Column(name="size", type="string", length=20, nullable=true)
     */
    protected ?string $size = null;

    /**
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    protected bool $public = false;

    public function __construct()
    {
        $this->environments = new ArrayCollection();

        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateOrder(): void
    {
        if (!isset($this->orderKey)) {
            $this->orderKey = 0;
        }
    }

    public function getId(): int
    {
        return Type::integer($this->id);
    }

    public function setName(?string $name): self
    {
        $this->name = $name ?? '';

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body ?? '';
    }

    public function setEditWithWysiwyg(?bool $editWithWysiwyg): self
    {
        $this->editWithWysiwyg = $editWithWysiwyg ?? false;

        return $this;
    }

    public function getEditWithWysiwyg(): bool
    {
        return $this->editWithWysiwyg;
    }

    public function setRenderOption(?string $renderOption): self
    {
        $this->renderOption = $renderOption ?? '';

        return $this;
    }

    public function getRenderOption(): string
    {
        return $this->renderOption;
    }

    public function setOrderKey(?int $orderKey): self
    {
        $this->orderKey = $orderKey ?? 0;

        return $this;
    }

    public function getOrderKey(): int
    {
        return $this->orderKey;
    }

    public function setAccumulateInOneFile(?bool $accumulateInOneFile): self
    {
        $this->accumulateInOneFile = $accumulateInOneFile ?? false;

        return $this;
    }

    public function getAccumulateInOneFile(): bool
    {
        return $this->accumulateInOneFile;
    }

    public function setPreview(?bool $preview): self
    {
        $this->preview = $preview ?? false;

        return $this;
    }

    public function getPreview(): bool
    {
        return $this->preview;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active ?? false;

        return $this;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRoleTo(string $roleTo): self
    {
        $this->roleTo = $roleTo;

        return $this;
    }

    public function getRoleTo(): string
    {
        return $this->roleTo;
    }

    public function setRoleCc(string $roleCc): self
    {
        $this->roleCc = $roleCc;

        return $this;
    }

    public function getRoleCc(): string
    {
        return $this->roleCc;
    }

    /**
     * @param string[] $circlesTo
     */
    public function setCirclesTo(array $circlesTo): self
    {
        $this->circlesTo = $circlesTo;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCirclesTo(): array
    {
        return $this->circlesTo ?? [];
    }

    public function setResponseTemplate(?string $responseTemplate): self
    {
        $this->responseTemplate = $responseTemplate;

        return $this;
    }

    public function getResponseTemplate(): string
    {
        return $this->responseTemplate ?? '';
    }

    public function setContentType(?ContentType $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?ContentType
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

    public function __toString(): string
    {
        return $this->name;
    }

    public function addEnvironment(Environment $environment): self
    {
        $this->environments[] = $environment;

        return $this;
    }

    public function removeEnvironment(Environment $environment): void
    {
        $this->environments->removeElement($environment);
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environments->toArray();
    }

    public function isEnvironmentExist(string $name): bool
    {
        foreach ($this->environments as $environment) {
            if ($environment->getname() === $name) {
                return true;
            }
        }

        return false;
    }

    public function setEmailContentType(?string $emailContentType): self
    {
        $this->emailContentType = $emailContentType;

        return $this;
    }

    public function getEmailContentType(): ?string
    {
        return $this->emailContentType;
    }

    public function setHeader(?string $header): self
    {
        $this->header = $header;

        return $this;
    }

    public function getHeader(): ?string
    {
        return $this->header;
    }

    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function setOrientation(?string $orientation): self
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): self
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

    public function getAllowOrigin(): ?string
    {
        return $this->allowOrigin;
    }

    public function setAllowOrigin(?string $allowOrigin): self
    {
        $this->allowOrigin = $allowOrigin;

        return $this;
    }

    public function getDisposition(): ?string
    {
        return $this->disposition;
    }

    public function setDisposition(?string $disposition): self
    {
        $this->disposition = $disposition;

        return $this;
    }

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
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
