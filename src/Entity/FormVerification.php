<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="form_verification")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class FormVerification
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="code", type="string", length=255)
     */
    private string $code;

    /**
     * @ORM\Column(name="created", type="datetime_immutable")
     */
    private \DateTimeImmutable $created;

    /**
     * @ORM\Column(name="expiration_date", type="datetime_immutable")
     */
    private \DateTimeImmutable $expirationDate;

    private const EXPIRATION_TIME = '+3 hours';

    public function __construct(/**
     * @ORM\Column(name="value", type="string", length=255)
     */
    private string $value)
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
