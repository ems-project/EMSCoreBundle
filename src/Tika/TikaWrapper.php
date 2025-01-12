<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tika;

use Symfony\Component\Process\Process;

/**
 * Copy from https://github.com/NinoSkopac/PhpTikaWrapper
 * Simple helper wrapper was outdated with a old dependency to symfony/process.
 */
class TikaWrapper
{
    public function __construct(private readonly string $tikaJar)
    {
    }

    /**
     * @throws \RuntimeException
     */
    protected function run(string $option, string $fileName): string
    {
        $file = new \SplFileInfo($fileName);

        $process = new Process(['java', '-jar', $this->tikaJar, $option, $file->getRealPath()]);
        $process->setWorkingDirectory(__DIR__);
        $process->run(function () {
        }, [
            'LANG' => 'en_US.utf-8',
        ]);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    public function getWordCount(string $fileName): int
    {
        return \str_word_count($this->getText($fileName));
    }

    public function getXHTML(string $filename): string
    {
        return $this->run('--xml', $filename);
    }

    public function getHTML(string $filename): string
    {
        return $this->run('--html', $filename);
    }

    public function getText(string $filename): string
    {
        return $this->run('--text', $filename);
    }

    public function getTextMain(string $filename): string
    {
        return $this->run('--text-main', $filename);
    }

    public function getMetadata(string $filename): string
    {
        return $this->run('--metadata', $filename);
    }

    public function getJson(string $filename): string
    {
        return $this->run('--json', $filename);
    }

    public function getXmp(string $filename): string
    {
        return $this->run('--xmp', $filename);
    }

    public function getLanguage(string $filename): string
    {
        return $this->run('--language', $filename);
    }

    public function getDocumentType(string $filename): string
    {
        return $this->run('--detect', $filename);
    }
}
