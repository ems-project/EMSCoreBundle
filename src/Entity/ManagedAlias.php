<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: ['name'], message: 'Name already exists!')]
class ManagedAlias extends JsonDeserializer implements \JsonSerializable, \Stringable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    /** @EMSAssert\AliasName() */
    protected string $name;
    protected ?string $label = null;
    private ?string $alias = null;
    /** @var array<mixed> */
    private array $indexes = [];
    private ?int $total = null;
    protected ?string $color = null;
    protected ?string $extra = null;

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ManagedAlias
    {
        $this->name = $name;

        return $this;
    }

    public function getAlias(): string
    {
        if (null === $this->alias) {
            throw new \RuntimeException('Unexpected null alias');
        }

        return $this->alias;
    }

    public function hasAlias(): bool
    {
        return null !== $this->alias;
    }

    public function setAlias(string $instanceId): void
    {
        $this->alias = $instanceId.$this->getName();
    }

    /**
     * @return array<mixed>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @param array<mixed> $indexes
     */
    public function setIndexes(array $indexes): self
    {
        $this->indexes = $indexes;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): ManagedAlias
    {
        $this->total = $total;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): ManagedAlias
    {
        $this->color = $color;

        return $this;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }

    public function setExtra(string $extra): ManagedAlias
    {
        $this->extra = $extra;

        return $this;
    }

    public function getLabel(): string
    {
        if (null === $this->label) {
            $replaced = \preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $this->name);
            if (!\is_string($replaced)) {
                $replaced = $this->name;
            }

            return \ucfirst(\strtolower(\trim($replaced)));
        }

        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->removeProperty('indexes');
        $json->removeProperty('total');
        $json->removeProperty('alias');

        return $json;
    }

    public static function fromJson(string $json, ?EntityInterface $managedAlias = null): ManagedAlias
    {
        $meta = JsonClass::fromJsonString($json);
        $managedAlias = $meta->jsonDeserialize($managedAlias);
        if (!$managedAlias instanceof ManagedAlias) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $managedAlias;
    }
}
