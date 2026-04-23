<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\MediaLibrary;

use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MEDIA_LIB_FOLDER_MOVE,
    description: 'Move media library folder',
    hidden: false
)]
class MediaLibraryFolderMoveCommand extends AbstractMediaLibraryCommand
{
    private MediaLibraryFolder $folder;
    private string $target;

    public const ARGUMENT_FOLDER_ID = 'folder-id';
    public const ARGUMENT_TARGET_ID = 'target-id';

    public function move(MediaLibraryDocument $document, string $to, ?string $from = null): void
    {
        $movedPath = $from ? $document->getPath()->renamePrefix($from, $to) : $document->getPath()->move($to);
        $document->setPath($movedPath);

        $this->mediaLibraryService->updateDocument($document, $this->getUsername());
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(self::ARGUMENT_FOLDER_ID, InputArgument::REQUIRED)
            ->addArgument(self::ARGUMENT_TARGET_ID, InputArgument::REQUIRED);
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Media Library - Move folder');

        $folderId = $this->getArgumentString(self::ARGUMENT_FOLDER_ID);
        $this->folder = $this->mediaLibraryService->getFolder($folderId);

        $targetId = $this->getArgumentString(self::ARGUMENT_TARGET_ID);
        $this->target = 'home' !== $targetId ? $this->mediaLibraryService->getFolder($targetId)->getPath()->getValue() : '/';
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobOutput = $output instanceof JobOutput ? $output : null;

        $fromPath = $this->folder->getPath();
        $from = $fromPath->getValue();
        $to = $this->target;
        $toChild = \implode('/', [$to, $fromPath->getName()]);
        $this->io->info(\sprintf('Start moving from "%s" to "%s"', $from, $to));

        $totalChildren = $this->mediaLibraryService->countChildren($from);
        $children = $this->mediaLibraryService->findChildrenByPath($from);
        $this->io->info(\sprintf('Found %d children to move', $totalChildren));

        $total = $totalChildren + 1;
        $processed = 0;
        $progressBar = $this->io->createProgressBar($total);

        foreach ($children as $child) {
            $this->move($child, $toChild, $from);

            ++$processed;
            $percentage = (int) (($processed / $total) * 100);

            $jobOutput?->progress($percentage);
            $progressBar->advance();
        }

        $this->io->info('Moving folder');
        $this->move($this->folder, $to);

        $jobOutput?->progress(100);
        $progressBar->finish();

        return self::EXECUTE_SUCCESS;
    }
}
