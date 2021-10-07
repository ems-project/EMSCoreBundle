<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\AssetExtractorService;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class AssetExtractCommand extends AbstractCommand
{
    private AssetExtractorService $extractorService;
    private StorageManager $storageManager;

    protected static $defaultName = Commands::ASSET_EXTRACT;

    public function __construct(AssetExtractorService $extractorService, StorageManager $storageManager)
    {
        parent::__construct();
        $this->extractorService = $extractorService;
        $this->storageManager = $storageManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Will extract data from all files found and load it in cache of the asset extractor service')
            ->addArgument(
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
