<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

class Session
{
    private ?string $id = null;

    /** @var resource */
    private $data;
    private ?int $time = null;
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
