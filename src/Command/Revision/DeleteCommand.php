<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DeleteCommand extends AbstractCommand
{
    protected static $defaultName = Commands::REVISION_DELETE;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Revision - Delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::EXECUTE_SUCCESS;
    }
}
