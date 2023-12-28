<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::RELEASE_PUBLISH,
    description: 'Publish scheduled releases.',
    hidden: false
)]
class PublishReleaseCommand extends AbstractCommand
{
    public function __construct(private readonly ReleaseService $releaseService)
    {
        parent::__construct();
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
