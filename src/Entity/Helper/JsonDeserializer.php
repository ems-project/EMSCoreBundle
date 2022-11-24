<?php

namespace EMS\CoreBundle\Entity\Helper;

use EMS\Helpers\Standard\Json;

abstract class JsonDeserializer
{
    /**
     * @param mixed $value
     */
    public function deserialize(string $name, $value): void
    {
        if ($this->isJsonClassArray($value)) {
            $subJson = JsonClass::fromJsonString(Json::encode($value));
            $value = $subJson->jsonDeserialize();
        }

        $this->deserializeProperty($name, $value);
    }

    /**
     * @param mixed $value
     */
    protected function deserializeProperty(string $name, $value): void
    {
        $dateFields = ['created', 'modified', 'lockUntil'];

        if (null !== $value && \in_array($name, $dateFields)) {
            $value = $this->convertToDateTime($value);
        }

        $this->{$name} = $value;
    }

    /**
     * @param array{'date': string, 'timezone': string} $value
     */
    protected function convertToDateTime(array $value): \DateTime
    {
        $time = $value['date'];
        $zone = new \DateTimeZone($value['timezone']);

        return new \DateTime($time, $zone);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    protected function deserializeArray(array $value): array
    {
        $deserialized = [];

        foreach ($value as $item) {
            $json = JsonClass::fromJsonString(Json::encode($item));
            $deserialized[] = $json->jsonDeserialize();
        }

        return $deserialized;
    }

    /**
     * @param mixed $array
     */
    private function isJsonClassArray($array): bool
    {
        if (!\is_array($array)) {
            return false;
        }

        $keys = [JsonClass::CLASS_INDEX, JsonClass::CONSTRUCTOR_ARGUMENTS_INDEX, JsonClass::PROPERTIES_INDEX];

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }
}
