<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Repository\JobRepository;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Output\Output;

class JobOutput extends Output
{
    private const JOB_VERBOSITY = self::VERBOSITY_NORMAL;
    private bool $newLine = true;

    public function __construct(private readonly JobRepository $jobRepository, private readonly int $jobId)
    {
        parent::__construct(self::JOB_VERBOSITY);
    }

    /**
     * Do not allow symfony to overwrite the verbosity level.
     */
    public function setVerbosity(int $level): void
    {
        parent::setVerbosity(self::JOB_VERBOSITY);
    }

    public function progress(int $progress): void
    {
        $job = $this->jobRepository->findById($this->jobId);
        $job->setProgress($progress);

        $this->jobRepository->save($job);
    }

    public function doWrite(string $message, bool $newline): void
    {
        if (!$newline && !$this->newLine && Json::isJson($message)) {
            $newline = true;
        }
        $this->newLine = $newline;
        $job = $this->jobRepository->findById($this->jobId);
        $job->setStatus($message);
        $job->setOutput(self::concatenateAnsiString($job->getOutput() ?? '', $this->getFormatter()->format($message) ?? '').($newline ? PHP_EOL : ''));

        $this->jobRepository->save($job);
    }

    public static function concatenateAnsiString(string $left, string $right): string
    {
        $pos = \strrpos($left, "\n");
        if (false === $pos) {
            $content = '';
            $lastLine = $left;
        } else {
            $content = \substr($left, 0, $pos + 1);
            $lastLine = \substr($left, $pos + 1);
        }
        $lines = \explode("\n", $right);
        $first = true;
        foreach ($lines as $line) {
            if ($first) {
                $first = false;
            } else {
                $content .= "\n";
            }
            $content .= self::concatenateAnsiLines($lastLine, $line);
        }

        return $content.$lastLine;
    }

    private static function concatenateAnsiLines(string &$lastLine, string $newLine): string
    {
        $newLine = \preg_replace("/.\x08/", '', $newLine);
        if (null === $newLine) {
            throw new \RuntimeException('Unexpected null');
        }

        \preg_match_all("/\e\[(?P<backspace>[0-9]+)D/", $newLine, $matches, PREG_OFFSET_CAPTURE);
        if (!empty($matches['backspace'])) {
            $line = '';
            $cursor = 0;
            foreach ($matches['backspace'] as $backspace) {
                $count = (int) $backspace[0];
                $line .= \substr($newLine, $cursor, $backspace[1] - 2);
                $line = \substr($line, 0, \strlen($line) - $count);
                $cursor = $backspace[1] + \strlen($backspace[0]) + 1;
            }
            $newLine = $line.\substr($newLine, $cursor);
        }

        \preg_match_all("/\e\[(?P<column>[0-9]+)G/", $newLine, $matches, PREG_OFFSET_CAPTURE);
        $cursor = 0;
        foreach ($matches['column'] as $column) {
            $lastLine .= \substr($newLine, $cursor, $column[1] - 2);
            $position = (int) $column[0];
            if ($position > \strlen($lastLine)) {
                $lastLine = \str_pad($lastLine, $position - 1, ' ', STR_PAD_RIGHT);
            } else {
                $lastLine = \substr($lastLine, $cursor, $position - 1);
            }
            $endOfLine = \substr($newLine, $column[1] + \strlen($column[0]) + 1);
            $cursor = $column[1] - 1;
        }
        if (!empty($matches['column'])) {
            $newLine = $endOfLine;
        }

        $output = $lastLine;
        $lastLine = $newLine;

        return $output;
    }
}
