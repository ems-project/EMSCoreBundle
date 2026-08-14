<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::ASSET_SYNCHRONIZE, description: 'Synchronize registered assets on storage services.', aliases: ['ems:asset:synchronize'], hidden: false)]
class SynchronizeAssetCommand extends AbstractCoreCommand
{
    /** @var string */
    protected $databaseName;
    /** @var string */
    protected $databaseDriver;

    public function __construct(protected LoggerInterface $logger, protected Registry $doctrine, protected ContentTypeService $contentTypeService, protected AssetExtractorService $extractorService, protected FileService $fileService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository(UploadedAsset::class);

        $storagesList = [];
        foreach ($this->fileService->getHealthStatuses() as $storageName => $health) {
            if ($health) {
                $storagesList[] = $storageName;
            }
        }

        if (\count($storagesList) < 2) {
            $this->io->error('There is nothing to synchronize as there is less than 2 healthy storage services');

            return Command::FAILURE;
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
                } catch (NotFoundException) {
                    $message = \sprintf('File not found %s', $hash['hash']);
                    $this->io->newLine();
                    $this->io->note($message);
                    ++$filesInError;
                } catch (\Throwable $e) {
                    ++$filesInError;
                    $message = \sprintf('Error with file identified by %s : %s', $hash['hash'], $e->getMessage());
                    $this->io->newLine();
                    $this->io->error($message);
                    $this->logger->warning($message);
                }
                $progress->advance();
            }
        }

        $progress->finish();
        $this->io->newLine();
        if ($filesInError > 0) {
            $this->io->note(\sprintf('%d files not found or in error', $filesInError));
        }

        return Command::SUCCESS;
    }
}
