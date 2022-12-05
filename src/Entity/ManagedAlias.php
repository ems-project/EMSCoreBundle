<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use EMS\Helpers\Standard\DateTime;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Managed Alias.
 *
 * @ORM\Table(name="managed_alias")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\ManagedAliasRepository")
 * @ORM\HasLifecycleCallbacks()
 */
#[UniqueEntity(fields: ['name'], message: 'Name already exists!')]
class ManagedAlias implements \Stringable
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @EMSAssert\AliasName()
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private string $name;

    /**
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected ?string $label = null;

    /**
     * @ORM\Column(name="alias", type="string", length=255, unique=true)
     */
    private ?string $alias = null;

    /**
     * @var array<mixed>
     */
    private array $indexes = [];

    private ?int $total = null;

    /**
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    private string $color;

    /**
     * @ORM\Column(name="extra", type="text", nullable=true)
     */
    private string $extra;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

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

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): ManagedAlias
    {
        $this->color = $color;

        return $this;
    }

    public function getExtra(): string
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
}
