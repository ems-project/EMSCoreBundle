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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MEDIA_LIB_FOLDER_RENAME,
    description: 'Rename media library folder',
    hidden: false
)]
class MediaLibraryFolderRenameCommand extends AbstractMediaLibraryCommand
{
    private MediaLibraryFolder $folder;
    private string $folderName;
    private string $username;

    public const ARGUMENT_FOLDER_ID = 'folder-id';
    public const ARGUMENT_FOLDER_NAME = 'folder-name';
    public const OPTION_USERNAME = 'username';

    public function rename(MediaLibraryDocument $document, string $from, string $to): void
    {
        $renamedPath = $document->getPath()->renamePrefix($from, $to);
        $document->setPath($renamedPath);

        $this->mediaLibraryService->updateDocument($document, $this->username);
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(self::ARGUMENT_FOLDER_ID, InputArgument::REQUIRED)
            ->addArgument(self::ARGUMENT_FOLDER_NAME, InputArgument::REQUIRED)
            ->addOption(self::OPTION_USERNAME, null, InputOption::VALUE_REQUIRED, 'media config hash');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Media Library - Rename folder');

        $folderId = $this->getArgumentString(self::ARGUMENT_FOLDER_ID);

        $this->folder = $this->mediaLibraryService->getFolder($folderId);
        $this->username = $this->getOptionString(self::OPTION_USERNAME);
        $this->folderName = $this->getArgumentString(self::ARGUMENT_FOLDER_NAME);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobOutput = $output instanceof JobOutput ? $output : null;

        $from = $this->folder->getPath()->getValue();
        $to = $this->folder->getPath()->setName($this->folderName)->getValue();
        $this->io->info(\sprintf('Start renaming from "%s" to "%s"', $from, $to));

        $totalChildren = $this->mediaLibraryService->countChildren($from);
        $children = $this->mediaLibraryService->findChildrenByPath($from);

        $this->io->info(\sprintf('Found %d children to renaming', $totalChildren));

        $total = $totalChildren + 1;
        $processed = 0;
        $progressBar = $this->io->createProgressBar($total);

        foreach ($children as $child) {
            $this->rename($child, $from, $to);

            ++$processed;
            $percentage = (int) (($processed / $total) * 100);

            $jobOutput?->progress($percentage);
            $progressBar->advance();
        }

        $this->io->info('Renaming folder');
        $this->rename($this->folder, $from, $to);

        $jobOutput?->progress(100);
        $progressBar->finish();

        return self::EXECUTE_SUCCESS;
    }
}
