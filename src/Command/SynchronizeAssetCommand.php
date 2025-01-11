<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ASSET_SYNCHRONIZE,
    description: 'Synchronize registered assets on storage services.',
    hidden: false,
    aliases: ['ems:asset:synchronize']
)]
class SynchronizeAssetCommand extends AbstractCommand
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
                } catch (NotFoundException) {
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
