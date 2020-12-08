<?php

namespace EMS\CoreBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

abstract class EmsCommand extends Command
{
    protected function formatStyles(OutputInterface &$output): void
    {
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('red', 'yellow', ['bold']));
        $output->getFormatter()->setStyle('comment', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('notice', new OutputFormatterStyle('blue', null));
    }
}
