<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Storage\Service\FileSystemStorage;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CopyFilesCommand extends Command implements CommandInterface
{
    /** @var FileService */
    private $fileService;
    /** @var string */
    private $storageFolder;

    protected static $defaultName = 'ems:copy:files';

    public function __construct(FileService $fileService, string $storageFoler = null)
    {
        parent::__construct();
        $this->fileService = $fileService;
        $this->storageFolder = $storageFoler;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Copy or symlink files too ems storage folder')
            ->addArgument('source', InputArgument::REQUIRED, 'source')
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'symlink files')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'pattern for filename')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $pattern = $input->getOption('pattern') ?? '*.*';
        $symlink = (bool) $input->getOption('symlink');
        $fileSystem = $this->getFileSystemStorage();

        $style = new SymfonyStyle($input, $output);
        $style->title(sprintf('%s files from %s\%s to %s', ($symlink ? 'Symlink' : 'Copy'), $source, $pattern, $this->storageFolder));

        $files = Finder::create()->in($source)->files()->name($pattern);

        $progress = $style->createProgressBar($files->count());
        $progress->start();

        foreach ($files as $file) {
            /** @var $file SplFileInfo */
            $hash = \sha1_file($file->getPathname());

            if ($fileSystem->head($hash)) {
                $progress->advance();
                continue;
            }

            if ($symlink) {
                $fileSystem->symlink($hash, $file->getPathname());
            } else {
                $fileSystem->create($hash, $file->getPathname());
            }

            $progress->advance();
        }

        $progress->finish();
    }

    private function getFileSystemStorage(): FileSystemStorage
    {
        $storageServiceId = sprintf('%s (%s)', FileSystemStorage::class, $this->storageFolder);

        $fileSystemStorage = $this->fileService->getStorageService($storageServiceId);

        if (null === $fileSystemStorage) {
            throw new \Exception('No file fileSystem storage found, define storage folder?');
        }

        return $fileSystemStorage;
    }
}
