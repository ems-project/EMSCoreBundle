<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Sessions
 *
 * @ORM\Table(name="session")
 * @ORM\Entity(repositoryClass="EMS\CoreBundle\Repository\SessionRepository")
 */
class Session
{
    /**
     * @var string
     *
     * @ORM\Column(name="id", type="string", length=128, nullable=false)
     *
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

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return Session
     */
    public function setId(string $id): Session
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return resource
     */
    public function getData(): resource
    {
        return $this->data;
    }

    /**
     * @param resource $data
     * @return Session
     */
    public function setData(resource $data): Session
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @param int $time
     * @return Session
     */
    public function setTime(int $time): Session
    {
        $this->time = $time;
        return $this;
    }

    /**
     * @return int
     */
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * @param int $lifetime
     * @return Session
     */
    public function setLifetime(int $lifetime): Session
    {
        $this->lifetime = $lifetime;
        return $this;
    }


}
