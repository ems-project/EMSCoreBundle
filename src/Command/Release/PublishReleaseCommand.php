<?php

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishReleaseCommand extends AbstractCommand
{
    protected static $defaultName = 'ems:release:scheduling';
    private ReleaseService $releaseService;
    private \DateTime $now;

    public function __construct(ReleaseService $releaseService)
    {
        parent::__construct();
        $this->releaseService = $releaseService;
    }

    protected function configure(): void
    {
        $this->setDescription('Publish scheduled releases');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Release - Publish');
        $this->now = new \DateTime();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $releases = $this->releaseService->findScheduling($this->now);

        if (empty($releases)) {
            $output->writeln('No scheduled release found');

            return parent::EXECUTE_SUCCESS;
        }

        foreach ($releases as $release) {
            $this->releaseService->publishRelease($release, false);
            $output->writeln('Release '.$release->getName().' has been published');
            $release->setStatus(ReleaseStatusEnumType::SCHEDULED_STATUS);
            $this->releaseService->update($release);
        }

        return parent::EXECUTE_SUCCESS;
    }
}
