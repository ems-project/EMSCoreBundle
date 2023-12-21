<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;

class MediaLibraryFile
{
    /** @var array{filename: string, sha1: string, filesize: int, mimetype: string} */
    public array $file;

    public string $path;
    public string $folder;
    public string $emsId;

    public function __construct(MediaLibraryConfig $config, public DocumentInterface $document)
    {
        $this->emsId = (string) $document->getEmsLink();

        $this->file = $document->getValue($config->fieldFile);
        $this->path = $document->getValue($config->fieldPath);
        $this->folder = $document->getValue($config->fieldFolder);
    }
}
