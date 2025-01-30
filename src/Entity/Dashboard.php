<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class Dashboard extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $name;
    protected string $icon;
    protected string $label;
    protected bool $sidebarMenu = true;
    protected bool $notificationMenu = false;
    protected ?string $definition = null;
    protected string $type;
    protected string $role;
    protected ?string $color = null;
    /** @var array<string, mixed> */
    protected array $options = [];
    protected int $orderKey;

    final public const string DEFINITION_LANDING_PAGE = 'landing_page';
    final public const string DEFINITION_QUICK_SEARCH = 'quick_search';
    final public const string DEFINITION_BROWSER_IMAGE = 'browser_image';
    final public const string DEFINITION_BROWSER_OBJECT = 'browser_object';
    final public const string DEFINITION_BROWSER_FILE = 'browser_file';

    final public const array DEFINITIONS = [
        self::DEFINITION_QUICK_SEARCH,
        self::DEFINITION_LANDING_PAGE,
        self::DEFINITION_BROWSER_IMAGE,
        self::DEFINITION_BROWSER_OBJECT,
        self::DEFINITION_BROWSER_FILE,
    ];

    final public const array DASHBOARD_BROWSERS = [
        self::DEFINITION_BROWSER_IMAGE,
        self::DEFINITION_BROWSER_OBJECT,
        self::DEFINITION_BROWSER_FILE,
    ];

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    #[\Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    #[\Override]
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

    public function getOption(string $option): ?string
    {
        return $this->getOptions()[$option];
    }

    public function getOptions(): DashboardOptions
    {
        return new DashboardOptions($this->options ?? []);
    }

    public function setOptions(DashboardOptions $options): void
    {
        $this->options = $options->getOptions();
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

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function setDefinition(?string $definition): void
    {
        $this->definition = $definition;
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

    #[\Override]
    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
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
