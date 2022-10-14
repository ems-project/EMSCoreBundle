<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="dashboard")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Dashboard extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name;

    /**
     * @ORM\Column(name="icon", type="text", length=255)
     */
    protected string $icon;

    /**
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected string $label;

    /**
     * @ORM\Column(name="sidebar_menu", type="boolean", options={"default" : 1})
     */
    protected bool $sidebarMenu = true;

    /**
     * @ORM\Column(name="notification_menu", type="boolean", options={"default" : 0})
     */
    protected bool $notificationMenu = false;

    /**
     * @ORM\Column(name="landing_page", type="boolean", options={"default" : 0})
     */
    protected bool $landingPage = false;

    /**
     * @ORM\Column(name="quick_search", type="boolean", options={"default" : 0})
     */
    protected bool $quickSearch = false;

    /**
     * @ORM\Column(name="type", type="string", length=2048)
     */
    protected string $type;

    /**
     * @ORM\Column(name="role", type="string", length=100)
     */
    protected string $role;

    /**
     * @ORM\Column(name="color", type="string", length=50, nullable=true)
     */
    protected ?string $color;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    protected array $options;

    /**
     * @ORM\Column(name="order_key", type="integer")
     */
    protected int $orderKey;

    public function __construct()
    {
        $now = new \DateTime();

        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->options = [];
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getName(): string
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

    public function isSidebarMenu(): bool
    {
        return $this->sidebarMenu;
    }

    public function setSidebarMenu(bool $sidebarMenu): void
    {
        $this->sidebarMenu = $sidebarMenu;
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function isLandingPage(): bool
    {
        return $this->landingPage;
    }

    public function setLandingPage(bool $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function isQuickSearch(): bool
    {
        return $this->quickSearch;
    }

    public function setQuickSearch(bool $quickSearch): void
    {
        $this->quickSearch = $quickSearch;
    }

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), __CLASS__);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }

    public static function fromJson(string $json, ?EntityInterface $dashboard = null): Dashboard
    {
        $meta = JsonClass::fromJsonString($json);
        $dashboard = $meta->jsonDeserialize($dashboard);
        if (!$dashboard instanceof Dashboard) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $dashboard;
    }
}
