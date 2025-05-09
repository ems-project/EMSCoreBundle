<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CacheAction
{
    use CreatedModifiedTrait;

    private UuidInterface $id;

    public function __construct(
        private readonly string $requestHash,
        /** @var array<mixed> */
        private readonly array $request,
        /** @var array<mixed> */
        private readonly array $response,
    ) {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    /**
     * @return array<mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    /**
     * @return array<mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
