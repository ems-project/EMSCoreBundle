<?php

namespace EMS\CoreBundle\Helper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;

class Archive
{
    /**
     * @var MimeTypes
     */
    private $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    public function extractToDirectory(string $filename): string
    {
        if (\is_dir($filename)) {
            return $filename;
        }

        switch ($this->guessMimeType($filename)) {
            case 'application/zip':
                return $this->unzip($filename);
                break;
            default:
                throw new \Exception('Unsupported archive type');
        }
    }

    private function guessMimeType(string $filename): string
    {
        return $this->mimeTypes->guessMimeType($filename);
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
        $workingDirectory = \tempnam(\sys_get_temp_dir(), 'ArchiveHelper');
        $filesystem = new Filesystem();
        $filesystem->remove($workingDirectory);
        $filesystem->mkdir($workingDirectory);

        return $workingDirectory;
    }
}
