<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AssetSynchronizeCommand extends AbstractCommand
{
    private Registry $doctrine;
    private FileService $fileService;

    protected static $defaultName = Commands::ASSET_SYNCHRONIZE;

    public function __construct(Registry $doctrine, FileService $fileService)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
    }

    protected function configure(): void
    {
        $this->setDescription('Synchronize registered assets on storage services');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');

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
