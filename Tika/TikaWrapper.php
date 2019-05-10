<?php

namespace EMS\CoreBundle\Tika;

use Symfony\Component\Process\Process;
use SplFileInfo;

/**
 * Copy from https://github.com/NinoSkopac/PhpTikaWrapper
 * Simple helper wrapper was outdated with a old dependency to symfony/process
 */
class TikaWrapper
{

    /**@var string */
    private $tikaJar;

    public function __construct(string $tikaJar)
    {
        $this->tikaJar = $tikaJar;
    }

    /**
     * @param string $option
     * @param string $fileName
     * @return string
     * @throws \RuntimeException
     */
    protected function run($option, $fileName)
    {
        $file = new SplFileInfo($fileName);
        $shellCommand = sprintf('java -jar %s %s "%s"', $this->tikaJar, $option, $file->getRealPath())
        ;

        $process = new Process($shellCommand);
        $process->setWorkingDirectory(__DIR__);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * @param string $fileName
     * @return int
     */
    public function getWordCount($fileName)
    {
        return str_word_count($this->getText($fileName));
    }

    /**
     * Options
     */

    /**
     * @param string $filename
     * @return string
     */
    public function getXHTML($filename)
    {
        return $this->run("--xml", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getHTML($filename)
    {
        return $this->run("--html", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getText($filename)
    {
        return $this->run("--text", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getTextMain($filename)
    {
        return $this->run("--text-main", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getMetadata($filename)
    {
        return $this->run("--metadata", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getJson($filename)
    {
        return $this->run("--json", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getXmp($filename)
    {
        return $this->run("--xmp", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getLanguage($filename)
    {
        return $this->run("--language", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getDocumentType($filename)
    {
        return $this->run("--detect", $filename);
    }
}
