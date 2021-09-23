<?php

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseSchedulingCommand extends AbstractCommand
{
    protected static $defaultName = 'ems:release:scheduling';
    private ReleaseService $releaseService;
    private \DateTime $now;
    private string $user = 'SYSTEM_RELEASE_SCHEDULING';

    public function __construct(ReleaseService $releaseService)
    {
        parent::__construct();
        $this->releaseService = $releaseService;
    }

    protected function configure(): void
    {
        $this->setDescription('Launch of scheduling releases');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Release - Scheduling');
        $this->now = new \DateTime();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $releases = $this->releaseService->findScheduling($this->now);

        if (empty($releases)) {
            $output->writeln('No scheduling release found');

            return -1;
        }

        foreach ($releases as $release) {
            $this->releaseService->publishRelease($release, false);
            $output->writeln('scheduling release '.$release->getName().' applied');
        }

        return 0;
    }
}
