<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: Commands::ASSET_HEAD,
    description: 'Loop over all known uploaded assets and update the seen information if the file is connected.',
    hidden: false,
    aliases: ['ems:asset:head']
)]
final class HeadAssetCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(protected LoggerInterface $logger, protected FileService $fileService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    #[\Override]
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
