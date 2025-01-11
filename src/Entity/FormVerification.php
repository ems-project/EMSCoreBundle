<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class FormVerification
{
    private readonly UuidInterface $id;

    private readonly string $code;
    private readonly \DateTimeImmutable $created;
    private \DateTimeImmutable $expirationDate;

    private const string EXPIRATION_TIME = '+3 hours';

    public function __construct(private readonly string $value)
    {
        $now = new \DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->expirationDate = $now->modify(self::EXPIRATION_TIME);
        $this->code = \sprintf('%06d', \random_int(1, 999999));
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function updateExpirationDate(): void
    {
        $now = new \DateTimeImmutable();
        $this->expirationDate = $now->modify(self::EXPIRATION_TIME);
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getExpirationDate(): \DateTimeImmutable
    {
        return $this->expirationDate;
    }
}
