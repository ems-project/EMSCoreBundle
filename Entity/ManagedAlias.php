<?php

namespace EMS\CoreBundle\Entity;

use EMS\CoreBundle\Validator\Constraints as EMSAssert;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\PrePersist;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Managed Alias
 *
 * @ORM\Table(name="managed_alias")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\ManagedAliasRepository")
 * @ORM\HasLifecycleCallbacks()
 *
 * @UniqueEntity(fields={"name"}, message="Name already exists!")
 */
class ManagedAlias
{
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
     *
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private $name;
    
    /**
     * @var string
     * @ORM\Column(name="alias", type="string", length=255, unique=true)
     */
    private $alias;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;
    
    /**
     * @var array
     */
    private $indexes = [];
    
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
    
    /**
     * Managed Alias
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }
    
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
  
    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified()
    {
        $this->modified = new \DateTime();
    }
    
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
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
    
    /**
     * @param string $instanceId
     */
    public function setAlias($instanceId)
    {
        $this->alias = $instanceId . $this->getName();
    }

    /**
     * @return array
     */
    public function getIndexes()
    {
        return $this->indexes;
    }
    
    /**
     * @param array $indexes
     *
     * @return ManagedAlias
     */
    public function setIndexes(array $indexes)
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
}
