<?php

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use EMS\CommonBundle\Entity\IdentifierIntegerTrait;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Helper\JsonDeserializer;
use EMS\Helpers\Standard\DateTime;

class WysiwygStylesSet extends JsonDeserializer implements \JsonSerializable, EntityInterface
{
    use CreatedModifiedTrait;
    use IdentifierIntegerTrait;

    protected string $name = '';
    protected string $config = '{}';
    protected int $orderKey = 0;
    protected ?string $formatTags = 'p;h1;h2;h3;h4;h5;h6;pre;address;div';
    protected string $tableDefaultCss = 'table table-border';
    protected ?string $contentCss = null;
    protected ?string $contentJs = null;
    /** @var ?array<string, mixed> */
    protected ?array $assets = null;
    protected ?string $saveDir = null;

    public function __construct()
    {
        $this->created = DateTime::create('now');
        $this->modified = DateTime::create('now');
    }

    public function setName(string $name): WysiwygStylesSet
    {
        $this->name = $name;

        return $this;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    public function setConfig(string $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig(): string
    {
        return $this->config;
    }

    /**
     * Set orderKey.
     *
     * @param int $orderKey
     *
     * @return WysiwygStylesSet
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

    public function getFormatTags(): ?string
    {
        return $this->formatTags;
    }

    public function setFormatTags(?string $formatTags): WysiwygStylesSet
    {
        $this->formatTags = $formatTags;

        return $this;
    }

    public function getContentCss(): ?string
    {
        return $this->contentCss;
    }

    public function setContentCss(?string $contentCss): WysiwygStylesSet
    {
        $this->contentCss = $contentCss;

        return $this;
    }

    /**
     * @return array<string,string|int>|null
     */
    public function getAssets(): ?array
    {
        return $this->assets;
    }

    /**
     * @param array<string, mixed>|null $assets
     */
    public function setAssets(?array $assets): WysiwygStylesSet
    {
        $this->assets = $assets;

        return $this;
    }

    public function getSaveDir(): ?string
    {
        return $this->saveDir;
    }

    public function setSaveDir(?string $saveDir): WysiwygStylesSet
    {
        $this->saveDir = $saveDir;

        return $this;
    }

    public function getTableDefaultCss(): string
    {
        return $this->tableDefaultCss;
    }

    public function setTableDefaultCss(?string $tableDefaultCss): WysiwygStylesSet
    {
        $this->tableDefaultCss = $tableDefaultCss ?? '';

        return $this;
    }

    public function getContentJs(): ?string
    {
        return $this->contentJs;
    }

    public function setContentJs(?string $contentJs): void
    {
        $this->contentJs = $contentJs;
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

    public static function fromJson(string $json, ?EntityInterface $styleSet = null): WysiwygStylesSet
    {
        $meta = JsonClass::fromJsonString($json);
        $styleSet = $meta->jsonDeserialize($styleSet);
        if (!$styleSet instanceof WysiwygStylesSet) {
            throw new \Exception(\sprintf('Unexpected object class, got %s', $meta->getClass()));
        }

        return $styleSet;
    }

    public function hasCSS(): bool
    {
        if (null === $this->contentCss || '' === $this->contentCss || !\is_string($this->assets[EmsFields::CONTENT_FILE_HASH_FIELD] ?? null)) {
            return false;
        }

        return true;
    }

    public function giveContentCss(): string
    {
        if (null === $this->contentCss) {
            throw new \RuntimeException('Unexpected null Content CSS');
        }

        return $this->contentCss;
    }

    public function giveAssetsHash(): string
    {
        $hash = $this->assets[EmsFields::CONTENT_FILE_HASH_FIELD] ?? null;
        if (!\is_string($hash)) {
            throw new \RuntimeException('Unexpected non string assets hash');
        }

        return $hash;
    }

    public function getCssEtag(): string
    {
        return \sha1(\implode('/', [
            $this->getName(),
            $this->giveContentCss(),
            $this->giveAssetsHash(),
        ]));
    }
}
