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
     * @ORM\Column(name="id", type="string", length=128, nullable=false)
     * @ORM\Id
     */
    private ?string $id = null;

    /**
     * @var resource
     *
     * @ORM\Column(name="data", type="blob", nullable=false)
     */
    private $data;

    /**
     * @ORM\Column(name="time", type="integer", options={"unsigned":true})
     */
    private ?int $time = null;

    /**
     * @ORM\Column(name="lifetime", type="integer")
     */
    private ?int $lifetime = null;

    public function getId(): string
    {
        if (null === $this->id) {
            throw new \RuntimeException('Unexpected null id');
        }

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
        if (null === $this->time) {
            throw new \RuntimeException('Unexpected null time');
        }

        return $this->time;
    }

    public function setTime(int $time): Session
    {
        $this->time = $time;

        return $this;
    }

    public function getLifetime(): int
    {
        if (null === $this->lifetime) {
            throw new \RuntimeException('Unexpected null lifetime');
        }

        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): Session
    {
        $this->lifetime = $lifetime;

        return $this;
    }
}
