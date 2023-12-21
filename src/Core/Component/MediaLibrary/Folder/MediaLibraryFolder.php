<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;

class MediaLibraryFolder
{
    private ?MediaLibraryFolder $parent = null;

    private function __construct(
        public readonly DocumentInterface $document,
        public readonly string $id,
        public readonly string $name,
        public readonly string $path,
    ) {
    }

    public static function fromDocument(MediaLibraryConfig $config, DocumentInterface $document): self
    {
        $path = $document->getValue($config->fieldPath);
        $name = \basename((string) $path);

        return new self($document, $document->getId(), $name, $path);
    }

    /**
     * @return MediaLibraryFolder[]
     */
    public function parents(): array
    {
        $parents = [$this];

        if ($this->parent) {
            $parents = \array_merge($this->parent->parents(), $parents);
        }

        return $parents;
    }

    public function getParentPath(): ?string
    {
        $path = \array_filter(\explode('/', $this->path));
        \array_pop($path);

        return $path ? '/'.\implode('/', $path) : null;
    }

    public function getParent(): ?MediaLibraryFolder
    {
        return $this->parent;
    }

    public function setParent(MediaLibraryFolder $parent): void
    {
        $this->parent = $parent;
    }
}
