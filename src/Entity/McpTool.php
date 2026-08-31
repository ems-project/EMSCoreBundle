<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Roles;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class McpTool extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    final public const string OUTPUT_TYPE_CONTENT_TYPE_ARRAY = 'content_type_array';
    final public const string OUTPUT_TYPE_JOB = 'job';
    final public const string OUTPUT_TYPE_CUSTOM = 'custom';

    private UuidInterface $id;
    protected string $name = '';
    protected string $label = '';
    protected string $role = Roles::ROLE_ADMIN;
    protected ?string $description = null;
    protected ?string $inputSchema = null;
    protected ?string $outputSchema = null;
    protected ?string $response = null;
    protected bool $enabled = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $mcpTool = null): McpTool
    {
        $meta = JsonClass::fromJsonString($json);
        $mcpTool = $meta->jsonDeserialize($mcpTool);
        if (!$mcpTool instanceof McpTool) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $mcpTool;
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

    public function getInputSchema(): ?string
    {
        return $this->inputSchema;
    }

    public function setInputSchema(?string $inputSchema): void
    {
        $this->inputSchema = $inputSchema;
    }

    public function getOutputSchema(): ?string
    {
        return $this->outputSchema;
    }

    public function setOutputSchema(?string $outputSchema): void
    {
        $this->outputSchema = $outputSchema;
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
