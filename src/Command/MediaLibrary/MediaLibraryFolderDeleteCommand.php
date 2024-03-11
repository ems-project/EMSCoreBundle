<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\MediaLibrary;

use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use MonorepoBuilderPrefix202311\Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MEDIA_LIB_FOLDER_DELETE,
    description: 'Delete media library folder',
    hidden: false
)]
class MediaLibraryFolderDeleteCommand extends AbstractMediaLibraryCommand
{
    private MediaLibraryFolder $folder;
    private string $username;

    public const ARGUMENT_FOLDER_ID = 'folder-id';
    public const OPTION_USERNAME = 'username';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(self::ARGUMENT_FOLDER_ID, InputArgument::REQUIRED)
            ->addOption(self::OPTION_USERNAME, null, InputOption::VALUE_REQUIRED, 'media config hash');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Media Library - Delete folder');

        $folderId = $this->getArgumentString(self::ARGUMENT_FOLDER_ID);
        $this->folder = $this->mediaLibraryService->getFolder($folderId);
        $this->username = $this->getOptionString(self::OPTION_USERNAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobOutput = $output instanceof JobOutput ? $output : null;

        $path = $this->folder->getPath()->getValue();
        $totalChildren = $this->mediaLibraryService->countByPath($path);
        $children = $this->mediaLibraryService->findByPath($path);

        $this->io->info(\sprintf('Found %d children to renaming', $totalChildren));

        $total = $totalChildren + 1;
        $processed = 0;
        $progressBar = $this->io->createProgressBar($total);

        foreach ($children as $child) {
            $this->mediaLibraryService->deleteDocument($child, $this->username);

            ++$processed;
            $percentage = (int) (($processed / $total) * 100);

            $jobOutput?->progress($percentage);
            $progressBar->advance();
        }

        $this->io->info('Deleting folder');
        $this->mediaLibraryService->deleteDocument($this->folder, $this->username);

        $jobOutput?->progress(100);
        $progressBar->finish();

        $this->mediaLibraryService->refresh();

        return self::EXECUTE_SUCCESS;
    }
}
