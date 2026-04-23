<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CoreBundle\Command\AbstractCoreCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISIONS_UNLOCK_ALL,
    description: 'Unlock all revisions'
)]
class UnLockAllCommand extends AbstractCoreCommand
{
    private const string ARG_USER = 'user';

    public function __construct(
        private readonly DataService $dataService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_USER, InputArgument::REQUIRED, 'User to lock all revisions')
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Revision - Unlock all revisions');

        $user = $this->getArgumentString(self::ARG_USER);

        $revisions = $this->dataService->unlockAllRevisions($user);
        $this->io->note(\sprintf('"%s" revisions have been unlocked.', $revisions));

        return self::SUCCESS;
    }
}
