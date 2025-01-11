<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

class View extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    protected string $name;
    protected string $type;
    protected string $label = '';
    protected ?string $icon = null;
    /** @var array<mixed> */
    protected ?array $options = null;
    protected int $orderKey = 0;
    protected ContentType $contentType;
    protected bool $public = false;
    protected ?string $role = null;
    protected ?string $definition = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __clone()
    {
        if ($this->id) {
            $this->created = DateTime::create('now');
            $this->modified = DateTime::create('now');
            $this->orderKey = 0;
        }
    }

    public function setName(string $name): View
    {
        $this->name = $name;

        return $this;
    }

    #[\Override]
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
        return $this->options ?? [];
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

    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
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

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?ViewDefinition $definition): void
    {
        $this->definition = $definition?->value;
    }
}
