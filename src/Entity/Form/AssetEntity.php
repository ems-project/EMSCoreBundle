<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CommonBundle\Helper\EmsFields;

class AssetEntity
{
    private string $filename;
    private string $hash;
    private ?float $x = null;
    private ?float $y = null;
    private ?float $width = null;
    private ?float $height = null;
    private ?float $rotate = null;
    private ?float $scaleX = null;
    private ?float $scaleY = null;
    private ?string $backgroundColor = null;
    /** @var array<string, mixed> */
    private array $config;

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return \array_merge($this->config, \array_filter([
            EmsFields::ASSET_CONFIG_TYPE => EmsFields::ASSET_CONFIG_TYPE_IMAGE,
            EmsFields::ASSET_CONFIG_RESIZE => 'crop',
            EmsFields::ASSET_CONFIG_X => $this->x,
            EmsFields::ASSET_CONFIG_Y => $this->y,
            EmsFields::ASSET_CONFIG_ROTATE => $this->rotate,
            EmsFields::ASSET_CONFIG_WIDTH => $this->width,
            EmsFields::ASSET_CONFIG_HEIGHT => $this->height,
            EmsFields::ASSET_CONFIG_FLIP_HORIZONTAL => (-1.0 === $this->scaleX),
            EmsFields::ASSET_CONFIG_FLIP_VERTICAL => (-1.0 === $this->scaleY),
            EmsFields::ASSET_CONFIG_BACKGROUND => $this->backgroundColor,
        ], fn ($value) => null !== $value));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->x = $config[EmsFields::ASSET_CONFIG_X] ?? null;
        $this->y = $config[EmsFields::ASSET_CONFIG_Y] ?? null;
        $this->rotate = $config[EmsFields::ASSET_CONFIG_ROTATE] ?? null;
        $this->width = $config[EmsFields::ASSET_CONFIG_WIDTH] ?? null;
        $this->height = $config[EmsFields::ASSET_CONFIG_HEIGHT] ?? null;
        $this->scaleX = ($config[EmsFields::ASSET_CONFIG_FLIP_HORIZONTAL] ?? false) ? -1.0 : 1.0;
        $this->scaleY = ($config[EmsFields::ASSET_CONFIG_FLIP_VERTICAL] ?? false) ? -1.0 : 1.0;
        $this->backgroundColor = $config[EmsFields::ASSET_CONFIG_BACKGROUND] ?? null;
    }

    /**
     * @return string[]
     */
    public function getFile(): array
    {
        return [
            EmsFields::CONTENT_FILE_NAME_FIELD => $this->filename,
            EmsFields::CONTENT_FILE_HASH_FIELD => $this->hash,
            EmsFields::CONTENT_MIME_TYPE_FIELD => $this->getMimetype(),
        ];
    }

    public function getX(): ?float
    {
        return $this->x;
    }

    public function setX(?float $x): void
    {
        $this->x = $x;
    }

    public function getY(): ?float
    {
        return $this->y;
    }

    public function setY(?float $y): void
    {
        $this->y = $y;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function getRotate(): ?float
    {
        return $this->rotate;
    }

    public function setRotate(?float $rotate): void
    {
        $this->rotate = $rotate;
    }

    public function getScaleX(): ?float
    {
        return $this->scaleX;
    }

    public function setScaleX(?float $scaleX): void
    {
        $this->scaleX = $scaleX;
    }

    public function getScaleY(): ?float
    {
        return $this->scaleY;
    }

    public function setScaleY(?float $scaleY): void
    {
        $this->scaleY = $scaleY;
    }

    public function getMimetype(): string
    {
        return $this->config[EmsFields::CONTENT_MIME_TYPE_FIELD_] ?? 'application/bin';
    }

    public function setMimetype(string $mimetype): void
    {
        $this->config[EmsFields::CONTENT_MIME_TYPE_FIELD_] = $mimetype;
    }

    public function getBackgroundColor(): ?string
    {
        return '#000000' === $this->backgroundColor ? null : $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): void
    {
        $this->backgroundColor = '' === $backgroundColor ? null : $backgroundColor;
    }
}
