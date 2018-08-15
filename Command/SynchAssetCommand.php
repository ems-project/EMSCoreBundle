<?php

// src/EMS/CoreBundle/Command/GreetCommand.php

namespace EMS\CoreBundle\Command;

use function count;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Storage\StorageInterface;
use Elasticsearch\Client;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Console\Input\InputOption;
use function unlink;

class SynchAssetCommand extends EmsCommand
{

    /**
     *
     *
     * @var Registry
     */
    protected $doctrine;
    /**
     *
     *
     * @var ContentTypeService
     */
    protected $contentTypeService;
    /**
     *
     *
     * @var AssetExtratorService
     */
    protected $extractorService;
    protected $databaseName;
    protected $databaseDriver;
    /**
     *
     *
     * @var FileService
     */
    protected $fileService;

    const PAGE_SIZE = 10;


    public function __construct(Logger $logger, Client $client, Session $session, Registry $doctrine, ContentTypeService $contentTypeService, AssetExtratorService $extractorService, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->contentTypeService = $contentTypeService;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;
        parent::__construct($logger, $client, $session);
    }

    protected function configure()
    {
        $this
            ->setName('ems:asset:synchronize')
            ->setDescription('Synchronize registered assets on all storage services');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager ();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository ( 'EMSCoreBundle:UploadedAsset' );

        $this->formatFlash($output);

        if(count($this->fileService->getStorages()) < 2)
        {
            $output->writeln('<error>There is nothing to synchronize as there is less than 2 storage services</error>');
            return;
        }

        // create a new progress bar
        $progress = new ProgressBar($output, $repository->countHashes());
        // start and displays the progress bar
        $progress->start();

        $page = 0;
        $fileNotFound = 0;
        while (true) {
            $hashes = $repository->getHashes($page);
            if(empty($hashes))
            {
                break;
            }
            ++$page;

            foreach ($hashes as $hash)
            {
                $file = $this->fileService->getFile($hash['hash']);

                if($file)
                {
                    /**@var StorageInterface $storage */
                    foreach ($this->fileService->getStorages() as $storage)
                    {
                        if(! $storage->head($hash['hash']))
                        {
                            $storage->create($hash['hash'], $file);
                        }
                    }

                    unlink($file);
                }
                else
                {
                    $output->writeln('');
                    $output->writeln('<comment>File not found '.$hash['hash'].'</comment>');
                    ++$fileNotFound;
                }

                $progress->advance();
            }
        }

        $progress->finish();
        $output->writeln('');
        if($fileNotFound)
        {

            $output->writeln('<comment>'.$fileNotFound.' files not found</comment>');
        }

    }

}
