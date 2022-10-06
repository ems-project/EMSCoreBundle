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
 * @UniqueEntity(fields={"name"}, message="Name already exists!")
 */
class ManagedAlias
{
    use CreatedModifiedTrait;
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @EMSAssert\AliasName()
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @ORM\Column(name="label", type="string", length=255, nullable=true)
     */
    protected ?string $label;

    /**
     * @var string
     *
     * @ORM\Column(name="alias", type="string", length=255, unique=true)
     */
    private $alias;

    /**
     * @var array<mixed>
     */
    private array $indexes = [];

    /**
     * @var int
     */
    private $total;

    /**
     * @var string
     *
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    private $color;

    /**
     * @var string
     *
     * @ORM\Column(name="extra", type="text", nullable=true)
     */
    private $extra;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return ManagedAlias
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
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

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param int $total
     *
     * @return ManagedAlias
     */
    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param string $color
     *
     * @return ManagedAlias
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return string
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * @param string $extra
     *
     * @return ManagedAlias
     */
    public function setExtra($extra)
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
