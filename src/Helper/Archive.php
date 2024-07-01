<?php

namespace EMS\CoreBundle\Helper;

use EMS\Helpers\File\TempDirectory;
use Symfony\Component\Mime\MimeTypes;

class Archive
{
    private readonly MimeTypes $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    public function extractToDirectory(string $filename): string
    {
        if (\is_dir($filename)) {
            return $filename;
        }

        return match ($this->guessMimeType($filename)) {
            'application/zip' => $this->unzip($filename),
            default => throw new \Exception('Unsupported archive type'),
        };
    }

    private function guessMimeType(string $filename): string
    {
        return $this->mimeTypes->guessMimeType($filename) ?? '';
    }

    private function unzip(string $filename): string
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($filename)) {
            throw new \Exception(\sprintf('Archive file %s can not be open', $filename));
        }

        $workingDirectory = $this->getWorkingDirectory();
        $zip->extractTo($workingDirectory);
        $zip->close();

        return $workingDirectory;
    }

    private function getWorkingDirectory(): string
    {
        $tempDirectory = TempDirectory::create();

        return $tempDirectory->path;
    }
}
