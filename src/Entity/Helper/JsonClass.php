<?php

namespace EMS\CoreBundle\Entity\Helper;

use Doctrine\ORM\PersistentCollection;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Entity\EntityInterface;

class JsonClass implements \JsonSerializable
{
    /** @var string */
    private $class;

    /** @var array */
    private $constructorArguments;

    /** @var array */
    private $properties;

    /** @var string[] */
    private array $replacedFields;

    public const CLASS_INDEX = 'class';
    public const CONSTRUCTOR_ARGUMNETS_INDEX = 'arguments';
    public const PROPERTIES_INDEX = 'properties';
    public const REPLACED_FIELDS = 'replaced';

    /**
     * @param string[] $replacedFields
     */
    public function __construct(array $properties, string $class, array $constructorArguments = [], array $replacedFields = [])
    {
        $this->class = $class;
        $this->constructorArguments = $constructorArguments;
        $this->properties = $properties;
        $this->replacedFields = $replacedFields;
    }

    public static function fromJsonString(string $jsonString)
    {
        $arguments = \json_decode($jsonString, true);

        return new self(
            $arguments[self::PROPERTIES_INDEX],
            $arguments[self::CLASS_INDEX],
            $arguments[self::CONSTRUCTOR_ARGUMNETS_INDEX],
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

    public function removeProperty(string $name): void
    {
        unset($this->properties[$name]);
    }

    public function updateProperty(string $name, $value): void
    {
        $this->properties[$name] = $value;
    }

    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

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
     * Specify data which should be serialized to JSON.
     *
     * @see https://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            self::CLASS_INDEX => $this->class,
            self::CONSTRUCTOR_ARGUMNETS_INDEX => $this->constructorArguments,
            self::PROPERTIES_INDEX => $this->properties,
            self::REPLACED_FIELDS => $this->replacedFields,
        ];
    }

    public function jsonDeserialize(object $object = null)
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

            $instance->deserialize($name, $value);
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
