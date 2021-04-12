<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class HeadAssetCommand extends Command
{
    protected static $defaultName = 'ems:asset:head';
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
        $this->setDescription('Loop over all known uploaded assets and update the seen information if the file is connected');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Update asset\'s seen information');

        $counter = $this->fileService->count();
        $this->io->progressStart($counter);
        $found = $from = 0;
        while ($from < $counter) {
            foreach ($this->fileService->get($from, 100, 'created', 'asc', '') as $assetUpload) {
                if (!$assetUpload instanceof UploadedAsset) {
                    throw new \RuntimeException('Unexpected UploadedAsset type');
                }
                if (\count($this->fileService->headIn($assetUpload)) > 0) {
                    ++$found;
                }
                ++$from;
                $this->io->progressAdvance();
            }
        }
        $this->io->progressFinish();
        if ($counter !== $found) {
            $this->io->warning(\sprintf('%d assets have not been found from %d', $counter - $found, $counter));
        } else {
            $this->io->success(\sprintf('%d assets have been found', $counter));
        }

        return 0;
    }
}
