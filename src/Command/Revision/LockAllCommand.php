<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISIONS_LOCK_ALL,
    description: 'Lock all revisions'
)]
class LockAllCommand extends AbstractCommand
{
    private const string ARG_USER = 'user';
    private const string OPTION_TIME = 'time';

    public function __construct(
        private readonly DataService $dataService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_USER, InputArgument::REQUIRED, 'User to lock all revisions')
            ->addOption(self::OPTION_TIME, null, InputOption::VALUE_REQUIRED, 'Lock time', '+10 minutes')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Revision - Lock all revisions');

        $user = $this->getArgumentString(self::ARG_USER);

        $until = new \DateTime()->modify($this->getOptionString(self::OPTION_TIME));
        $this->io->comment(\sprintf('Lock all revisions until "%s"', $until->format('Y-m-d H:i:s')));

        $revisions = $this->dataService->lockAllRevisions($until, $user);
        $this->io->note(\sprintf('"%s" revisions have been locked.', $revisions));

        return self::SUCCESS;
    }
}
