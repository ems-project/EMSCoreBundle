<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class HeadAssetCommand extends Command
{
    protected FileService $fileService;
    protected LoggerInterface $logger;
    private SymfonyStyle $io;

    public function __construct(LoggerInterface $logger, FileService $fileService)
    {
        $this->fileService = $fileService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:asset:head')
            ->setDescription('Loop over all known uploaded assets and update the seen information if the file is connected');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Update asset\'s seen information');

        return 0;
    }
}
