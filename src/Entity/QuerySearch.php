<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="query_search")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class QuerySearch implements EntityInterface
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
     * @var \Datetime
     *
     * @ORM\Column(name="created", type="datetime")
     */
    private $created;

    /**
     * @var \Datetime
     *
     * @ORM\Column(name="modified", type="datetime")
     */
    private $modified;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    private $label;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var Environment[]
     * @ORM\ManyToMany(targetEntity="Environment", inversedBy="contentTypesHavingThisAsDefault")
     * @ORM\JoinColumn(name="environment_id", referencedColumnName="id")
     */
    protected $environments;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    private $options;

    /**
     * @var int
     *
     * @ORM\Column(name="order_key", type="integer")
     */
    private $orderKey;

    public function __construct()
    {
        $now = new \DateTime();
        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->modified = $now;
        $this->environments = [];
        $this->options = [
            'searchConfig' => '{}',
            'query' => '',
        ];
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateModified(): void
    {
        $this->modified = new \DateTime();
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    /**
     * @param Environment[] $environments
     */
    public function setEnvironments(array $environments): void
    {
        $this->environments = $environments;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOrderKey(): int
    {
        return $this->orderKey ?? 0;
    }

    public function setOrderKey(int $orderKey): void
    {
        $this->orderKey = $orderKey;
    }
}
