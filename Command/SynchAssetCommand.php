<?php

// src/EMS/CoreBundle/Command/GreetCommand.php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\HttpFoundation\Session\Session;
use function count;
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
            ->setDescription('Synchronize registered assets on storage services')
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'All storage services will be synchronized'
            );
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

        $serviceId = count($this->fileService->getStorages());
        if(! $input->getOption('all') ){
            /**@var QuestionHelper $helper*/
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the storage service to synchronize',
                array_merge($this->fileService->getStorages(), ['All']),
                0
            );
            $question->setErrorMessage('Service %s is invalid.');

            $service = $helper->ask($input, $output, $question);


            if( $service != 'All' )
            {
                $serviceId = array_search ( $service ,$this->fileService->getStorages() );
                $output->writeln('You have just selected: '.$service.' ('.$serviceId.')');
            }
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
                    if($serviceId == count($this->fileService->getStorages()))
                    {
                        /**@var StorageInterface $storage */
                        foreach ($this->fileService->getStorages() as $storage)
                        {
                            if(! $storage->head($hash['hash']))
                            {
                                if(!$storage->create($hash['hash'], $file))
                                {
                                    $output->writeln('');
                                    $output->writeln('<comment>EMS was not able to synchronize on the service '.$storage.'</comment>');
                                }
                            }
                        }
                    }
                    else {
                        /**@var StorageInterface $storage */
                        $storage = $this->fileService->getStorages()[$serviceId];
                        if(! $storage->head($hash['hash']))
                        {
                            if(!$storage->create($hash['hash'], $file))
                            {
                                $output->writeln('');
                                $output->writeln('<comment>EMS was not able to synchronize on the service '.$storage.'</comment>');
                            }
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
