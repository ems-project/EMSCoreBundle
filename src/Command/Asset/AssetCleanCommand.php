<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Asset;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AssetCleanCommand extends AbstractCommand
{
    private Registry $doctrine;
    private FileService $fileService;

    protected static $defaultName = Commands::ASSET_CLEAN;

    public function __construct(Registry $doctrine, FileService $fileService)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->fileService = $fileService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Unreference useless assets (no files are deleted from storages)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var UploadedAssetRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:UploadedAsset');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');

        $progress = new ProgressBar($output, $repository->countHashes());
        $progress->start();

        $page = 0;
        $filesDereference = 0;
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
                if (0 === $usedCounter) {
                    $repository->dereference($hash['hash']);
                    ++$filesDereference;
                } else {
                    ++$filesInUsed;
                    $totalCounter += $usedCounter;
                }
                $progress->advance();
            }
        }

        $progress->finish();
        $output->writeln('');
        if ($filesDereference) {
            $output->writeln("<comment>$filesDereference files have been dereferenced</comment>");
        }
        if ($filesInUsed) {
            $output->writeln("<comment>$filesInUsed files are referenced $totalCounter times</comment>");
        }

        return 0;
    }
}
