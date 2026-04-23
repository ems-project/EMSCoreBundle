<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::ASSET_CLEAN, description: 'Unreference useless assets (no files are deleted from storages).', aliases: ['ems:asset:clean'], hidden: false)]
class CleanAssetCommand extends AbstractCoreCommand
{
    public function __construct(protected LoggerInterface $logger, protected Registry $doctrine, protected FileService $fileService)
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
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository(Revision::class);

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
        $this->io->newLine();
        if (0 !== $filesDereference) {
            $this->io->note(\sprintf('%d files have been dereferenced', $filesDereference));
        }
        if (0 !== $filesInUsed) {
            $this->io->note(\sprintf('%d files are referenced %d times', $filesInUsed, $totalCounter));
        }

        return 0;
    }
}
