<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use function Symfony\Component\String\u;

class MediaLibraryPath implements \Countable
{
    /**
     * @param string[] $value
     */
    public function __construct(public readonly array $value)
    {
    }

    public static function fromString(string $path): self
    {
        return new self(\array_filter(\explode('/', $path)));
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->value);
    }

    public function getFolderValue(): string
    {
        return $this->parent()?->getValue().'/';
    }

    public function getLabel(): string
    {
        return \implode(' / ', $this->value);
    }

    public function getName(): string
    {
        return \basename($this->getValue());
    }

    public function getValue(): string
    {
        return '/'.\implode('/', $this->value);
    }

    public function move(string $location): self
    {
        $locationPath = self::fromString($location);

        return new self([...$locationPath->value, $this->getName()]);
    }

    public function parent(): ?self
    {
        $path = $this->value;
        \array_pop($path);

        return $path ? new self($path) : null;
    }

    public function renamePrefix(string $from, string $to): self
    {
        $newPath = u($this->getValue())->trimPrefix($from)->prepend($to)->toString();

        return self::fromString($newPath);
    }

    public function setName(string $name): self
    {
        $path = $this->value;
        \array_pop($path);

        return new self([...$path, $name]);
    }
}
