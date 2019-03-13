<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\Storage\StorageInterface;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class CleanAssetCommand extends EmsCommand
{
    const PAGE_SIZE = 10;
    /**
     *
     *
     * @var Registry
     */
    protected $doctrine;
    /**
     *
     *
     * @var FileService
     */
    protected $fileService;

    public function __construct(Logger $logger, Client $client, Session $session, Registry $doctrine, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
        parent::__construct($logger, $client, $session);
    }

    protected function configure()
    {
        $this
            ->setName('ems:asset:clean')
            ->setDescription('Clean unreferenced assets on storage services (!even if the storage is shared)');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');

        $this->formatFlash($output);

        // create a new progress bar
        $progress = new ProgressBar($output, $repository->countHashes());
        // start and displays the progress bar
        $progress->start();

        $page = 0;
        $filesCleaned = 0;
        $filesInUsed = 0;
        $totalCounter = 0;
        while (true) {
            $hashes = $repository->getHashes($page);
            if (empty($hashes)) {
                break;
            }
            ++$page;

            foreach ($hashes as $hash) {
                $usedCounter = $revRepo->hashReferenced($hash['hash']);
                if ($usedCounter === 0) {
                    /**@var StorageInterface $storage */
                    foreach ($this->fileService->getStorages() as $storage) {
                        $storage->remove($hash['hash']);
                    }
                    $repository->dereference($hash['hash']);

                    ++$filesCleaned;
                } else {
                    ++$filesInUsed;
                    $totalCounter += $usedCounter;
                }
                $progress->advance();
            }
        }

        $progress->finish();
        $output->writeln('');
        if ($filesCleaned) {
            $output->writeln("<comment>$filesCleaned files have been cleaned</comment>");
        }
        if ($filesInUsed) {
            $output->writeln("<comment>$filesInUsed files are referenced $totalCounter times</comment>");
        }
    }
}
