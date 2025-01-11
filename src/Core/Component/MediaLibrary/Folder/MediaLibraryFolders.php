<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Folder;

use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Common\PropertyAccess\PropertyPath;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryPath;

class MediaLibraryFolders
{
    /** @var array<MediaLibraryFolder> */
    public array $folders = [];

    private readonly PropertyAccessor $propertyAccessor;

    public function __construct(private readonly MediaLibraryConfig $config)
    {
        $this->propertyAccessor = PropertyAccessor::createPropertyAccessor();
    }

    public function addDocument(DocumentInterface $document): void
    {
        $folder = new MediaLibraryFolder($document, $this->config);
        $this->folders[$folder->getPath()->getValue()] = $folder;
    }

    /**
     * @return array<string, string>
     */
    public function getChoices(): array
    {
        $choices = ['Home' => 'home'];

        foreach ($this->getFolders() as $folder) {
            $choices[$folder->getPath()->getLabel()] = $folder->id;
        }

        return $choices;
    }

    /**
     * @return array<string, array{ folder: MediaLibraryFolder, children: array<string, mixed> }>
     */
    public function getStructure(): array
    {
        $structure = [];

        foreach ($this->getFolders() as $folder) {
            if (0 === \count($folder->getPath())) {
                continue;
            }

            $parentProperty = $folder->getPath()->parent() ? $this->createStructurePath($folder->getPath()->parent()) : null;
            if ($parentProperty && null === $this->propertyAccessor->getValue($structure, $parentProperty)) {
                continue;
            }

            $folderProperty = $this->createStructurePath($folder->getPath());
            $this->propertyAccessor->setValue($structure, $folderProperty, ['folder' => $folder]);
        }

        return $structure;
    }

    private function createStructurePath(MediaLibraryPath $path): PropertyPath
    {
        return new PropertyPath(\sprintf('[%s]', \implode('][children][', $path->value)));
    }

    /**
     * @return MediaLibraryFolder[]
     */
    private function getFolders(): array
    {
        $folders = $this->folders;
        \ksort($folders);

        return $folders;
    }
}
