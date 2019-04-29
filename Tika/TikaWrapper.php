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
    /**
     * @param string $option
     * @param string $fileName
     * @return string
     * @throws \RuntimeException
     */
    protected static function run($option, $fileName)
    {
        $file = new SplFileInfo($fileName);
        $shellCommand = 'java -jar tika-app-1.14.jar ' . $option . ' "' . $file->getRealPath() . '"';

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
    public static function getWordCount($fileName)
    {
        return str_word_count(self::getText($fileName));
    }

    /**
     * Options
     */

    /**
     * @param string $filename
     * @return string
     */
    public static function getXHTML($filename)
    {
        return self::run("--xml", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getHTML($filename)
    {
        return self::run("--html", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getText($filename)
    {
        return self::run("--text", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getTextMain($filename)
    {
        return self::run("--text-main", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getMetadata($filename)
    {
        return self::run("--metadata", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getJson($filename)
    {
        return self::run("--json", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getXmp($filename)
    {
        return self::run("--xmp", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getLanguage($filename)
    {
        return self::run("--language", $filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function getDocumentType($filename)
    {
        return self::run("--detect", $filename);
    }
}
