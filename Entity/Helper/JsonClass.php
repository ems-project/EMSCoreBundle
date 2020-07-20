<?php

namespace EMS\CoreBundle\Entity\Helper;

use Doctrine\ORM\PersistentCollection;
use EMS\CoreBundle\Entity\ContentType;

class JsonClass implements \JsonSerializable
{
    /** @var string */
    private $class;

    /** @var array */
    private $constructorArguments;

    /** @var array */
    private $properties;

    const CLASS_INDEX = 'class';
    const CONSTRUCTOR_ARGUMNETS_INDEX = 'arguments';
    const PROPERTIES_INDEX = 'properties';

    public function __construct(array $properties, string $class, array $constructorArguments = [])
    {
        $this->class = $class;
        $this->constructorArguments = $constructorArguments;
        $this->properties = $properties;
    }

    public static function fromJsonString(string $jsonString)
    {
        $arguments = \json_decode($jsonString, true);

        return new self(
            $arguments[self::PROPERTIES_INDEX],
            $arguments[self::CLASS_INDEX],
            $arguments[self::CONSTRUCTOR_ARGUMNETS_INDEX]
        );
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
        return array_key_exists($name, $this->properties);
    }

    public function handlePersistentCollections(...$properties): void
    {
        foreach ($properties as $property) {
            if (! $this->hasProperty($property) || ! $this->properties[$property] instanceof PersistentCollection) {
                continue;
            }
            $value = $this->properties[$property]->toArray();
            $this->updateProperty($property, $value);
        }
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            self::CLASS_INDEX => $this->class,
            self::CONSTRUCTOR_ARGUMNETS_INDEX => $this->constructorArguments,
            self::PROPERTIES_INDEX => $this->properties,
        ];
    }

    public function jsonDeserialize(object $object = null)
    {
        $reflectionClass = new \ReflectionClass($this->class);
        $instance = $object;
        if ($instance === null) {
            $instance = $reflectionClass->newInstance(...$this->constructorArguments);
        }

        foreach ($this->properties as $name => $value) {
            if (! $reflectionClass->hasProperty($name)) {
                continue;
            }

            $instance->deserialize($name, $value);
        }

        return $instance;
    }
}
