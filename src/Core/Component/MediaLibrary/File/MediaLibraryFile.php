<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\File;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MediaLibraryFile extends MediaLibraryDocument
{
    /** @var array{filename: string, sha1: string, filesize: int, mimetype: string} */
    public array $file;

    public function __construct(
        public DocumentInterface $document,
        private readonly MediaLibraryConfig $config,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($this->document, $this->config);

        $this->file = $document->getValue($config->fieldFile);
    }

    public function urlView(): string
    {
        return $this->urlGenerator->generate('ems.file.view', [
            'sha1' => $this->file['sha1'],
            'type' => $this->file['mimetype'],
            'name' => $this->getName(),
        ]);
    }
}
