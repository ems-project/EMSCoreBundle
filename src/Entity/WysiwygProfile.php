<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;

class WysiwygProfile extends JsonDeserializer implements \JsonSerializable, EntityInterface, \Stringable
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    public const string CKEDITOR4 = 'ckeditor4';
    public const string CKEDITOR5 = 'ckeditor5';
    protected string $name = '';
    protected string $editor = self::CKEDITOR4;
    protected ?string $config = null;
    protected int $orderKey = 0;

    public function __construct()
    {
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
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

    public function getEditor(): string
    {
        return $this->editor;
    }

    public function setEditor(string $editor): void
    {
        $this->editor = $editor;
    }
}
