<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="session")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SessionRepository")
 */
class Session
{
    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=128, nullable=false)
     * @ORM\Id
     */
    private $id;

    /**
     * @var resource
     *
     * @ORM\Column(name="data", type="blob", nullable=false)
     */
    private $data;

    /**
     * @var int
     *
     * @ORM\Column(name="time", type="integer", options={"unsigned":true})
     */
    private $time;

    /**
     * @var int
     *
     * @ORM\Column(name="lifetime", type="integer")
     */
    private $lifetime;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): Session
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return resource
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param resource $data
     */
    public function setData($data): Session
    {
        $this->data = $data;

        return $this;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function setTime(int $time): Session
    {
        $this->time = $time;

        return $this;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): Session
    {
        $this->lifetime = $lifetime;

        return $this;
    }
}
