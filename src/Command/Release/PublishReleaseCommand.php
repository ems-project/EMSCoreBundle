<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishReleaseCommand extends AbstractCommand
{
    protected static $defaultName = Commands::RELEASE_PUBLISH;
    private ReleaseService $releaseService;

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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Release - Publish');

        $releases = $this->releaseService->findReadyAndDue();

        if (empty($releases)) {
            $output->writeln('No scheduled release found');

            return parent::EXECUTE_SUCCESS;
        }

        foreach ($releases as $release) {
            $this->releaseService->publishRelease($release, true);
            $output->writeln(\sprintf('Release %s has been published', $release->getName()));
        }

        return parent::EXECUTE_SUCCESS;
    }
}
