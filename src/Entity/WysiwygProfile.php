<?php

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

class WysiwygProfile extends JsonDeserializer implements \JsonSerializable, EntityInterface, \Stringable
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    protected string $name = '';
    protected ?string $config = null;
    protected int $orderKey = 0;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function setName(string $name): WysiwygProfile
    {
        $this->name = $name;

        return $this;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return WysiwygProfile
     */
    public function setOrderKey($orderKey)
    {
        $this->orderKey = $orderKey;

        return $this;
    }

    /**
     * Get orderKey.
     *
     * @return int
     */
    public function getOrderKey()
    {
        return $this->orderKey;
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

    public static function fromJson(string $json, ?EntityInterface $profile = null): WysiwygProfile
    {
        $meta = JsonClass::fromJsonString($json);
        $profile = $meta->jsonDeserialize($profile);
        if (!$profile instanceof WysiwygProfile) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $profile;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getName();
    }
}
