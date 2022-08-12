<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\Helpers\Standard\DateTime;

trait CreatedModifiedTrait
{
    /**
     * @ORM\Column(name="created", type="datetime")
     */
    private \DateTimeInterface $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     */
    private \DateTimeInterface $modified;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = DateTime::create('now');
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    public function getModified(): \DateTimeInterface
    {
        return $this->modified;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function setModified(\DateTimeInterface $modified): self
    {
        $this->modified = $modified;

        return $this;
    }
}
