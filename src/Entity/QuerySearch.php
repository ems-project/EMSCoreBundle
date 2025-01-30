<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class QuerySearch extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;
    protected string $label;
    protected string $name = '';
    /** @var Collection <int,Environment> */
    protected Collection $environments;
    /** @var array<string, mixed> */
    protected array $options = ['query' => '{}'];
    protected int $orderKey = 9999;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
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

    #[\Override]
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

    #[\Override]
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

    #[\Override]
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
