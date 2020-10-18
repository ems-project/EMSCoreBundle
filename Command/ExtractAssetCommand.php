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

    protected function configure()
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = new Finder();
        $fileIterator = $finder->in($input->getArgument('path'))->files()->name($input->getArgument('name'));

        $progress = new ProgressBar($output, $fileIterator->count());
        // start and displays the progress bar
        $progress->start();

        /** @var SplFileInfo $file */
        foreach ($fileIterator as $file) {
            $hash = $this->storageManager->computeFileHash($file->getRealPath());
            if (is_string($file->getRealPath())) {
                $this->extractorService->extractData($hash, $file->getRealPath());
            }
            $progress->advance();
        }
        $progress->finish();
    }
}
