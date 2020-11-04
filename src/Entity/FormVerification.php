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
     * @var UuidInterface
     *
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=255)
     */
    private $value;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=255)
     */
    private $code;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created", type="datetime_immutable")
     */
    private $created;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="expiration_date", type="datetime_immutable")
     */
    private $expirationDate;

    private const EXPIRATION_TIME = '+3 hours';

    public function __construct(string $value)
    {
        $now = new \DateTimeImmutable();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->expirationDate = $now->modify(self::EXPIRATION_TIME);

        $this->value = $value;
        $this->code = sprintf("%06d", mt_rand(1, 999999));
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
}
