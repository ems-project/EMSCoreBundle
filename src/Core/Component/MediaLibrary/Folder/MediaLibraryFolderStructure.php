<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Common\PropertyAccess\PropertyPath;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;

class MediaLibraryFolderStructure
{
    /**
     * @var array<int, array{ id: string, name: string, path: string }>
     */
    public array $folders = [];

    /**
     * @param iterable<DocumentInterface> $documents
     */
    private function __construct(private readonly MediaLibraryConfig $config, iterable $documents)
    {
        foreach ($documents as $document) {
            $path = (string) $document->getValue($this->config->fieldPath);
            $this->folders[] = [
                'id' => $document->getId(),
                'name' => \basename($path),
                'path' => $path,
            ];
        }
    }

    /**
     * @param iterable<DocumentInterface> $documents
     */
    public static function create(MediaLibraryConfig $config, iterable $documents): self
    {
        return new self($config, $documents);
    }

    /**
     * @return array<string, array{ id: string, name: string, path: string, children: array<string, mixed> }>
     */
    public function toArray(): array
    {
        $structure = [];
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();

        foreach ($this->folders as $folder) {
            $folderPath = \array_filter(\explode('/', $folder['path']));

            if (0 === \count($folderPath)) {
                continue;
            }

            $path = \sprintf('[%s]', \implode('][children][', $folderPath));
            $propertyPath = new PropertyPath($path);

            $propertyAccessor->setValue($structure, $propertyPath, $folder);
        }

        return $structure;
    }
}
