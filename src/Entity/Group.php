<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Group extends JsonDeserializer implements EntityInterface, \JsonSerializable, \Stringable
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $name;
    protected ?string $label = null;
    /** @var mixed[] */
    protected array $roles = [];

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function setId(UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isLabelDefined(): bool
    {
        return null !== $this->label;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return \array_values(\array_unique($this->roles));
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = \array_values(\array_unique($roles));
    }

    public static function fromJson(string $json, ?EntityInterface $group = null): Group
    {
        $meta = JsonClass::fromJsonString($json);
        $group = $meta->jsonDeserialize($group);
        if (!$group instanceof Group) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $group;
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function jsonSerialize(): mixed
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }
}
