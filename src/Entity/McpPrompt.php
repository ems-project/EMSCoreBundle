<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Roles;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class McpPrompt extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $name = '';
    protected string $label = '';
    protected string $role = Roles::ROLE_ADMIN;
    protected ?string $description = null;
    protected ?string $arguments = null;
    protected ?string $response = null;
    protected bool $enabled = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $mcpPrompt = null): McpPrompt
    {
        $meta = JsonClass::fromJsonString($json);
        $mcpPrompt = $meta->jsonDeserialize($mcpPrompt);
        if (!$mcpPrompt instanceof McpPrompt) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $mcpPrompt;
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    public function setArguments(?string $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }
}
