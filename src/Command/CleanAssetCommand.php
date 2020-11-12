<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanAssetCommand extends EmsCommand
{
    /** @var Registry */
    protected $doctrine;

    /** @var FileService */
    protected $fileService;

    public function __construct(LoggerInterface $logger, Client $client, Registry $doctrine, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
        parent::__construct($logger, $client);
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:asset:clean')
            ->setDescription('Clean unreferenced assets on storage services (!even if the storage is shared)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');

        $this->formatStyles($output);

        $progress = new ProgressBar($output, $repository->countHashes());
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
        return 0;
    }
}
