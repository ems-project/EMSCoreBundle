<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

/**
 * @ORM\Table(name="view")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class View extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected int $id;

    /**
     * @ORM\Column(name="created", type="datetime")
     */
    protected \DateTime $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     */
    protected \DateTime $modified;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected string $name;

    /**
     * @ORM\Column(name="type", type="string", length=255)
     */
    protected string $type;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @ORM\Column(name="icon", type="string", length=255, nullable=true)
     */
    protected ?string $icon;

    /**
     * @var array<mixed>
     * @ORM\Column(name="options", type="json_array", nullable=true)
     */
    protected array $options;

    /**
     * @ORM\Column(name="order_key", type="integer")
     */
    protected int $orderKey = 0;

    /**
     * @ORM\ManyToOne(targetEntity="ContentType", inversedBy="views")
     * @ORM\JoinColumn(name="content_type_id", referencedColumnName="id")
     */
    protected ContentType $contentType;

    /**
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    protected bool $public = false;

    /**
     * @ORM\Column(name="role", type="string", length=100, nullable=true)
     */
    protected ?string $role;

    public function __clone()
    {
        if ($this->id) {
            $now = new \DateTime('now');
            $this->created = $now;
            $this->modified = $now;
            $this->orderKey = 0;
        }
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = new \DateTime();
        if (!isset($this->created)) {
            $this->created = $this->modified;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCreated(\DateTime $created): View
    {
        $this->created = $created;

        return $this;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public function setModified(\DateTime $modified): View
    {
        $this->modified = $modified;

        return $this;
    }

    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    public function setName(string $name): View
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setType(string $type): View
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setIcon(?string $icon): View
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param array<mixed> $options
     */
    public function setOptions(array $options): View
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOrderKey(int $orderKey): View
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    public function getOrderKey(): int
    {
        return $this->orderKey;
    }

    public function setContentType(ContentType $contentType): View
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): View
    {
        $this->public = $public;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function jsonSerialize()
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('contentType');

        return $json;
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
