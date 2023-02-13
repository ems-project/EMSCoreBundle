<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="query_search")
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class QuerySearch extends JsonDeserializer implements \JsonSerializable, EntityInterface
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
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected string $label;

    /**
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    protected string $name = '';

    /**
     * @var Collection <int,Environment>
     *
     * @ORM\ManyToMany(targetEntity="Environment", cascade={"persist"})
     *
     * @ORM\JoinTable(name="environment_query_search",
     *      joinColumns={@ORM\JoinColumn(name="query_search_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="environment_id", referencedColumnName="id")}
     *      )
     */
    protected Collection $environments;

    /**
     * @var array<string, mixed>
     *
     * @ORM\Column(name="options", type="json", nullable=true)
     */
    protected array $options = ['query' => '{}'];

    /**
     * @ORM\Column(name="order_key", type="integer")
     */
    protected int $orderKey = 9999;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
        $this->environments = new ArrayCollection();
    }

    public static function fromJson(string $json, ?\EMS\CommonBundle\Entity\EntityInterface $querySearch = null): QuerySearch
    {
        $meta = JsonClass::fromJsonString($json);
        $querySearch = $meta->jsonDeserialize($querySearch);
        if (!$querySearch instanceof QuerySearch) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $querySearch;
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $webalizedName = Encoder::webalize($name);
        $this->name = $webalizedName;
    }

    public function addEnvironment(Environment $environment): QuerySearch
    {
        $this->environments[] = $environment;

        return $this;
    }

    public function removeEnvironment(Environment $environment): void
    {
        $this->environments->removeElement($environment);
    }

    /**
     * @return array <int, Environment>
     */
    public function getEnvironments(): array
    {
        return $this->environments->toArray();
    }

    /**
     * @param Collection<int, Environment> $environments
     */
    public function setEnvironments(Collection $environments): void
    {
        $this->environments = $environments;
    }

    /**
     * @param string $name
     */
    public function isEnvironmentExist($name): bool
    {
        foreach ($this->environments as $environment) {
            if ($environment->getName() === $name) {
                return true;
            }
        }

        return false;
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
        return $this->orderKey ?? 9999;
    }

    public function setOrderKey(int $orderKey): void
    {
        $this->orderKey = $orderKey;
    }

    public function jsonSerialize(): JsonClass
    {
        $json = new JsonClass(\get_object_vars($this), self::class);
        $json->removeProperty('id');
        $json->removeProperty('created');
        $json->removeProperty('modified');
        $json->replaceCollectionByEntityNames('environments');

        return $json;
    }
}
