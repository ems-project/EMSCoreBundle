<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\Helpers\Standard\DateTime;

/**
 * @ORM\Table(name="sequence")
 *
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SequenceRepository")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Sequence
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Column(name="id", type="integer")
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="value", type="integer", nullable=false)
     */
    private int $value = 1;

    /**
     * @ORM\Version @ORM\Column(name="version", type="integer", nullable=false)
     */
    private int $version = 0;

    public function __construct(/**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
        private string $name)
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    /**
     * @ORM\PrePersist
     *
     * @ORM\PreUpdate
     */
    public function updateVersion(): void
    {
        ++$this->version;
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
     * Set name.
     *
     * @param string $name
     *
     * @return Sequence
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set value.
     *
     * @param int $value
     *
     * @return Sequence
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value.
     *
     * @return int
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * inc.
     *
     * @return int
     */
    public function inc()
    {
        $this->value = $this->value + 1;

        return $this->value;
    }

    /**
     * Get version.
     *
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }
}
