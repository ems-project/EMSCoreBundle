<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger, Registry $doctrine, ContentTypeService $contentTypeService, AssetExtractorService $extractorService, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->contentTypeService = $contentTypeService;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:asset:synchronize')
            ->setDescription('Synchronize registered assets on storage services');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository(UploadedAsset::class);

        $this->formatStyles($output);

        $storagesList = [];
        foreach ($this->fileService->getHealthStatuses() as $storageName => $health) {
            if ($health) {
                $storagesList[] = $storageName;
            }
        }

        if (\count($storagesList) < 2) {
            $output->writeln('<error>There is nothing to synchronize as there is less than 2 healthy storage services</error>');

            return 1;
        }

        $progress = new ProgressBar($output, $repository->countHashes());
        $progress->start();

        $page = 0;
        $filesInError = 0;
        while (true) {
            $hashes = $repository->getHashes($page);
            if (empty($hashes)) {
                break;
            }
            ++$page;

            foreach ($hashes as $hash) {
                try {
                    $this->fileService->synchroniseAsset($hash['hash']);
                } catch (NotFoundException $e) {
                    $message = \sprintf('File not found %s', $hash['hash']);
                    $output->writeln('');
                    $output->writeln(\sprintf('<comment>%s</comment>', $message));
                    ++$filesInError;
                } catch (\Throwable $e) {
                    ++$filesInError;
                    $message = \sprintf('Error with file identified by %s : %s', $hash['hash'], $e->getMessage());
                    $output->writeln('');
                    $output->writeln(\sprintf('<error>%s</error>', $message));
                    $this->logger->warning($message);
                }
                $progress->advance();
            }
        }

        $progress->finish();
        $output->writeln('');
        if ($filesInError > 0) {
            $output->writeln(\sprintf('<comment>%d files not found or in error</comment>', $filesInError));
        }

        return 0;
    }
}
