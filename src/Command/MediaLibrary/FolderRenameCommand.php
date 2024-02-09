<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\MediaLibrary;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfigFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Folder\MediaLibraryFolder;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryDocument;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryService;
use MonorepoBuilderPrefix202311\Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MEDIA_LIB_FOLDER_RENAME,
    description: 'Rename media library folder',
    hidden: false
)]
class FolderRenameCommand extends AbstractCommand
{
    private MediaLibraryFolder $folder;
    private MediaLibraryConfig $config;
    private string $folderName;
    private string $username;

    public const ARGUMENT_FOLDER_ID = 'folder-id';
    public const ARGUMENT_FOLDER_NAME = 'folder-name';
    public const OPTION_HASH = 'hash';
    public const OPTION_USERNAME = 'username';

    public function __construct(
        private readonly MediaLibraryConfigFactory $configFactory,
        private readonly MediaLibraryService $mediaLibraryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_FOLDER_ID, InputArgument::REQUIRED)
            ->addArgument(self::ARGUMENT_FOLDER_NAME, InputArgument::REQUIRED)
            ->addOption(self::OPTION_HASH, null, InputOption::VALUE_REQUIRED, 'media config hash')
            ->addOption(self::OPTION_USERNAME, null, InputOption::VALUE_REQUIRED, 'media config hash')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Media Library - Rename folder');

        $hash = $this->getOptionString(self::OPTION_HASH);
        $folderId = $this->getArgumentString(self::ARGUMENT_FOLDER_ID);

        /** @var MediaLibraryConfig $config */
        $config = $this->configFactory->createFromHash($hash);

        $this->config = $config;
        $this->folder = $this->mediaLibraryService->getFolder($config, $folderId);
        $this->username = $this->getOptionString(self::OPTION_USERNAME);
        $this->folderName = $this->getArgumentString(self::ARGUMENT_FOLDER_NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobOutput = $output instanceof JobOutput ? $output : null;

        $from = $this->folder->getPath()->getValue();
        $to = $this->folder->getPath()->setName($this->folderName)->getValue();
        $this->io->info(\sprintf('Start renaming from "%s" to "%s"', $from, $to));

        $totalChildren = $this->mediaLibraryService->countByPath($this->config, $from);
        $children = $this->mediaLibraryService->findByPath($this->config, $from);

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

        $this->mediaLibraryService->refresh($this->config);

        return self::EXECUTE_SUCCESS;
    }

    public function rename(MediaLibraryDocument $document, string $from, string $to): void
    {
        $renamedPath = $document->getPath()->move($from, $to);
        $document->setPath($renamedPath);

        $this->mediaLibraryService->updateDocument($document, $this->username);
    }
}
