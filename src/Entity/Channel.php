<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="channel")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Channel extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="uuid", unique=true)
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class=UuidGenerator::class)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="alias", type="string", length=255)
     */
    protected $alias;

    /**
     * @var bool
     *
     * @ORM\Column(name="public", type="boolean", options={"default" : 0})
     */
    protected $public = false;

    /**
     * @var string
     *
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected $label;

    /**
     * @var ?array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    protected ?array $options = [
        'translationContentType' => 'label',
        'routeContentType' => 'route',
        'templateContentType' => 'template',
        'searchConfig' => '{}',
    ];

    /**
     * @ORM\Column(name="order_key", type="integer")
     */
    protected int $orderKey = 0;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $channel = null): Channel
    {
        $meta = JsonClass::fromJsonString($json);
        $channel = $meta->jsonDeserialize($channel);
        if (!$channel instanceof Channel) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $channel;
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

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): void
    {
        $this->alias = $alias;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return \array_filter($this->options ?? []);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(?array $options = null): void
    {
        $this->options = $options;
    }

    public function getOrderKey(): int
    {
        return $this->orderKey;
    }

    public function setOrderKey(int $orderKey): void
    {
        $this->orderKey = $orderKey;
    }

    public function getEntryPath(): ?string
    {
        $entryPath = $this->getOptions()['entryPath'] ?? null;

        if (!\is_string($entryPath) || '' === $entryPath) {
            return null;
        }
        if (!\str_starts_with($entryPath, '/')) {
            $entryPath = '/'.$entryPath;
        }
        if ('/' === $entryPath) {
            $entryPath = '';
        }

        return \sprintf('/channel/%s%s', $this->getName(), $entryPath);
    }

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');

        return $json;
    }
}
