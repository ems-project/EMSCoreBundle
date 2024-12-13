<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\AssetExtractorService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(
    name: Commands::ASSET_EXTRACT,
    description: 'Will extract data from all files found and load it in cache of the asset extractor service.',
    hidden: false,
    aliases: ['ems:asset:extract']
)]
class ExtractAssetCommand extends AbstractCommand
{
    public function __construct(protected LoggerInterface $logger, protected AssetExtractorService $extractorService, protected StorageManager $storageManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to the files to extract data from'
        )
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'File pattern or file name i.e. *.pdf',
                '*.*'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected name argument');
        }
        $path = $input->getArgument('path');
        if (!\is_string($path)) {
            throw new \RuntimeException('Unexpected path argument');
        }

        $finder = new Finder();
        $fileIterator = $finder->in($path)->files()->name($name);

        $progress = new ProgressBar($output, $fileIterator->count());
        $progress->start();

        /** @var SplFileInfo $file */
        foreach ($fileIterator as $file) {
            $realPath = $file->getRealPath();
            if (false === $realPath) {
                $progress->advance();
                continue;
            }

            $hash = $this->storageManager->computeFileHash($realPath);
            if (\is_string($file->getRealPath())) {
                $this->extractorService->extractData($hash, $realPath);
            }
            $progress->advance();
        }
        $progress->finish();

        return 0;
    }
}
