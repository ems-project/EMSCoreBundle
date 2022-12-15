<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Elastica\Document;

class MediaLibraryFile
{
    private function __construct(
        private readonly string $path,
        private readonly string $name,
        public string|int $size,
        public ?string $type,
        private readonly string $hash,
    ) {
    }

    public static function createFromDocument(MediaLibraryConfig $config, Document $document): self
    {
        $file = $document->get($config->fieldFile);

        return new self(
            (string) $document->get($config->fieldPath),
            $file['filename'],
            $file['filesize'] ?? 0,
            $file['mimetype'] ?? null,
            $file['sha1']
        );
    }

    /**
     * @return array{ path: string, file?: array{name: string, size: string, type: string, hash: string } }
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'file' => [
                'name' => $this->name,
                'size' => (string) $this->size,
                'type' => (string) $this->type,
                'hash' => $this->hash,
            ],
        ];
    }
}
