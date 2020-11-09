<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class SynchronizeAssetCommand extends EmsCommand
{

    /** @var Registry */
    protected $doctrine;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var AssetExtractorService */
    protected $extractorService;
    /** @var string */
    protected $databaseName;
    /** @var string */
    protected $databaseDriver;
    /** @var FileService */
    protected $fileService;
    /** @var int  */
    const PAGE_SIZE = 10;


    public function __construct(Logger $logger, Client $client, Registry $doctrine, ContentTypeService $contentTypeService, AssetExtractorService $extractorService, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->contentTypeService = $contentTypeService;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;
        parent::__construct($logger, $client);
    }

    protected function configure(): void
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


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');

        $this->formatStyles($output);

        $storages = [];
        foreach ($this->fileService->getStorages() as $storage) {
            $storages[$storage->__toString()] = $storage;
        }

        if (\count($storages) < 2) {
            $output->writeln('<error>There is nothing to synchronize as there is less than 2 storage services</error>');
            return 1;
        }


        if (! $input->getOption('all')) {
            /**@var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the storage services to synchronize',
                array_keys($storages),
                0
            );
            $question->setMultiselect(true);
            $question->setErrorMessage('Service %s is invalid.');

            $serviceLabels = $helper->ask($input, $output, $question);
            $storagesToSynchronize = [];
            foreach ($serviceLabels as $serviceLabel) {
                if (isset($storages[$serviceLabel])) {
                    $storagesToSynchronize[] = $storages[$serviceLabel];
                }
            }
        } else {
            $storagesToSynchronize = $storages;
        }

        foreach ($storagesToSynchronize as $service) {
            $output->writeln('You have selected: ' . $service->__toString());
        }


        $progress = new ProgressBar($output, $repository->countHashes());
        $progress->start();

        $page = 0;
        $fileNotFound = 0;
        while (true) {
            $hashes = $repository->getHashes($page);
            if (empty($hashes)) {
                break;
            }
            ++$page;

            foreach ($hashes as $hash) {
                $file = $this->fileService->getFile($hash['hash']);

                if ($file === null) {
                    $output->writeln('');
                    $output->writeln('<comment>File not found ' . $hash['hash'] . '</comment>');
                    ++$fileNotFound;
                    $progress->advance();
                    continue;
                }

                foreach ($storagesToSynchronize as $storage) {
                    if (!$storage->head($hash['hash']) && !$storage->create($hash['hash'], $file)) {
                        $output->writeln('');
                        $output->writeln('<comment>EMS was not able to synchronize on the service ' . $storage . '</comment>');
                    }
                }

                unlink($file);
                $progress->advance();
            }
        }

        $progress->finish();
        $output->writeln('');
        if ($fileNotFound > 0) {
            $output->writeln('<comment>' . $fileNotFound . ' files not found</comment>');
        }
        return 0;
    }
}
