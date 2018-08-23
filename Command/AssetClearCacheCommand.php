<?php

// src/EMS/CoreBundle/Command/GreetCommand.php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Storage\StorageInterface;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use function count;

class AssetClearCacheCommand extends EmsCommand
{
    /**
     *
     *
     * @var FileService
     */
    protected $fileService;


    public function __construct(Logger $logger, Client $client, Session $session, FileService $fileService)
    {
        $this->fileService = $fileService;
        parent::__construct($logger, $client, $session);
    }

    protected function configure()
    {
        $this
            ->setName('ems:asset:clear-cache')
            ->setDescription('Clear storage service\'s caches');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->formatFlash($output);

        // create a new progress bar
        $progress = new ProgressBar($output, count($this->fileService->getStorages()));
        // start and displays the progress bar
        $progress->start();


        /**@var StorageInterface $storage */
        foreach ($this->fileService->getStorages() as $storage)
        {
            if($storage->supportCacheStore())
            {
                $storage->clearCache();
            }
            $progress->advance();
        }


        $progress->finish();
        $output->writeln('');
        $output->writeln('<comment>Elasticms assets caches cleared</comment>');

    }

}
