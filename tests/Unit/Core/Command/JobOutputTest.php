<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Command;

use EMS\CoreBundle\Command\JobOutput;
use PHPUnit\Framework\TestCase;

class JobOutputTest extends TestCase
{
    public function testSimpleConcatenation(): void
    {
        self::assertEquals('foobar', JobOutput::concatenateAnsiString('foo', 'bar'));
        self::assertEquals("foo\nbar", JobOutput::concatenateAnsiString("foo\n", 'bar'));
        self::assertEquals("foo\n", JobOutput::concatenateAnsiString("foo\nbar", "\e[1G"));
        self::assertEquals("foo\n", JobOutput::concatenateAnsiString("foo\nbar", "Toto\e[1G"));
        self::assertEquals("foo\nb", JobOutput::concatenateAnsiString("foo\nbar", "\e[2G"));
        self::assertEquals("foo\nbar      ", JobOutput::concatenateAnsiString("foo\nbar", "\e[10G"));
        self::assertEquals("foo\nYes", JobOutput::concatenateAnsiString("foo\nbar", "Toto\e[1GNo\e[1GYes"));
        self::assertEquals('couille', JobOutput::concatenateAnsiString('', "coq\x08uille"));
        self::assertEquals('couille', JobOutput::concatenateAnsiString('', "coq\033[1Duille"));
        self::assertEquals('cuille', JobOutput::concatenateAnsiString('', "coq\033[2Duille"));
    }

    public function testProgressBar(): void
    {
        $output = '';
        $rewind = "\e[1G";
        $output = JobOutput::concatenateAnsiString($output, '[>-----------------]');
        self::assertEquals('[>-----------------]', $output);
        $output = JobOutput::concatenateAnsiString($output, $rewind);
        $output = JobOutput::concatenateAnsiString($output, '[=>----------------]');
        self::assertEquals('[=>----------------]', $output);
        $output = JobOutput::concatenateAnsiString($output, $rewind);
        $output = JobOutput::concatenateAnsiString($output, '[==>---------------]');
        self::assertEquals('[==>---------------]', $output);
    }
}
