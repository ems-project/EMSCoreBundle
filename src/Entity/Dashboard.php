<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="dashboard")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Dashboard implements EntityInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="created", type="datetime")
     */
    private \Datetime $created;

    /**
     * @ORM\Column(name="modified", type="datetime")
     */
    private \Datetime $modified;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    private string $name;

    /**
     * @ORM\Column(name="icon", type="text", length=255)
     */
    private string $icon;

    /**
     * @ORM\Column(name="label", type="string", length=255)
     */
    private string $label;

    /**
     * @ORM\Column(name="side_menu", type="boolean", options={"default" : 1})
     */
    private bool $sideMenu = true;

    /**
     * @ORM\Column(name="notification_menu", type="boolean", options={"default" : 0})
     */
    private bool $notificationMenu = false;

    /**
     * @ORM\Column(name="type", type="string", length=2048)
     */
    protected string $type;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    private array $options;

    /**
     * @ORM\Column(name="order_key", type="integer")
     */
    private int $orderKey;

    public function __construct()
    {
        $now = new \DateTime();

        $this->id = Uuid::uuid4();
        $this->created = $now;
        $this->modified = $now;
        $this->options = [];
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getCreated(): \Datetime
    {
        return $this->created;
    }

    public function getModified(): \Datetime
    {
        return $this->modified;
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

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function isSideMenu(): bool
    {
        return $this->sideMenu;
    }

    public function setSideMenu(bool $sideMenu): void
    {
        $this->sideMenu = $sideMenu;
    }

    public function isNotificationMenu(): bool
    {
        return $this->notificationMenu;
    }

    public function setNotificationMenu(bool $notificationMenu): void
    {
        $this->notificationMenu = $notificationMenu;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
