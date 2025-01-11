<?php

namespace EMS\CoreBundle\Entity\Helper;

use Doctrine\ORM\PersistentCollection;
use EMS\CoreBundle\Entity\EntityInterface;
use EMS\Helpers\Standard\Json;

class JsonClass implements \JsonSerializable
{
    final public const string CLASS_INDEX = 'class';
    final public const string CONSTRUCTOR_ARGUMENTS_INDEX = 'arguments';
    final public const string PROPERTIES_INDEX = 'properties';
    final public const string REPLACED_FIELDS = 'replaced';

    /**
     * @param array<string, mixed> $properties
     * @param class-string         $class
     * @param array<mixed>         $constructorArguments
     * @param string[]             $replacedFields
     */
    public function __construct(
        private array $properties,
        private readonly string $class,
        private readonly array $constructorArguments = [],
        private array $replacedFields = [],
    ) {
        $proxyFields = ['__initializer__', '__cloner__', '__isInitialized__'];
        $this->properties = \array_filter(
            $this->properties,
            static fn ($v, $k) => !\in_array($k, $proxyFields, true),
            \ARRAY_FILTER_USE_BOTH
        );
    }

    public static function fromJsonString(string $jsonString): self
    {
        $arguments = Json::decode($jsonString);

        return new self(
            $arguments[self::PROPERTIES_INDEX],
            $arguments[self::CLASS_INDEX],
            $arguments[self::CONSTRUCTOR_ARGUMENTS_INDEX],
            $arguments[self::REPLACED_FIELDS] ?? [],
        );
    }

    /**
     * @return string[]
     */
    public static function getCollectionEntityNames(string $json, string $property): array
    {
        $arguments = Json::decode($json);

        return $arguments[self::PROPERTIES_INDEX][$property] ?? [];
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getProperty(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    public function removeProperty(string $name): void
    {
        unset($this->properties[$name]);
    }

    public function updateProperty(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }

    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    /**
     * @param string $properties
     */
    public function handlePersistentCollections(...$properties): void
    {
        foreach ($properties as $property) {
            if (!$this->hasProperty($property) || !$this->properties[$property] instanceof PersistentCollection) {
                continue;
            }
            $value = $this->properties[$property]->toArray();
            $this->updateProperty($property, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            self::CLASS_INDEX => $this->class,
            self::CONSTRUCTOR_ARGUMENTS_INDEX => $this->constructorArguments,
            self::PROPERTIES_INDEX => $this->properties,
            self::REPLACED_FIELDS => $this->replacedFields,
        ];
    }

    public function jsonDeserialize(?object $object = null): ?object
    {
        $reflectionClass = new \ReflectionClass($this->class);
        $instance = $object;
        if (null === $instance) {
            $instance = $reflectionClass->newInstance(...$this->constructorArguments);
        }

        foreach ($this->properties as $name => $value) {
            if (!$reflectionClass->hasProperty($name)) {
                continue;
            }
            if (\in_array($name, $this->replacedFields)) {
                continue;
            }

            if (\method_exists($instance, 'deserialize')) {
                $instance->deserialize($name, $value);
            }
        }

        return $instance;
    }

    public function replaceCollectionByEntityNames(string $property): void
    {
        $this->replacedFields[] = $property;
        $names = [];
        if (isset($this->properties[$property])) {
            foreach ($this->properties[$property]->toArray() as $entity) {
                if (!$entity instanceof EntityInterface) {
                    throw new \RuntimeException('Unexpected collection entity type');
                }
                $names[] = $entity->getName();
            }
        }
        $this->updateProperty($property, $names);
    }
}
