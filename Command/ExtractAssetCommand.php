<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Service\AssetExtractorService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ExtractAssetCommand extends EmsCommand
{

    /** @var AssetExtractorService */
    protected $extractorService;
    /** @var StorageManager */
    protected $storageManager;


    public function __construct(Logger $logger, Client $client, AssetExtractorService $extractorService, StorageManager $storageManager)
    {
        $this->extractorService = $extractorService;
        $this->storageManager = $storageManager;
        parent::__construct($logger, $client);
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:asset:extract')
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
            if ($realPath === false) {
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
