<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\DocumentService;
use EMS\Helpers\File\TempDirectory;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: Commands::CONTENT_TYPE_IMPORT,
    description: 'Import json files from a zip file as content type\'s documents.',
    hidden: false,
    aliases: ['ems:contenttype:import']
)]
class DocumentCommand extends Command
{
    final public const string COMMAND = 'ems:contenttype:import';

    private const string ARGUMENT_CONTENT_TYPE = 'content-type-name';
    private const string ARGUMENT_ARCHIVE = 'archive';
    private const string OPTION_BULK_SIZE = 'bulk-size';
    private const string OPTION_RAW = 'raw';
    private const string OPTION_DONT_SIGN_DATA = 'dont-sign-data';
    private const string OPTION_FORCE = 'force';
    private const string OPTION_DONT_FINALIZE = 'dont-finalize';
    private const string OPTION_BUSINESS_KEY = 'business-key';
    private ?SymfonyStyle $io = null;
    private ?ContentType $contentType = null;
    private ?string $archiveFilename = null;

    public function __construct(private readonly ContentTypeService $contentTypeService, private readonly DocumentService $documentService, private readonly DataService $dataService, private readonly string $defaultBulkSize)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARGUMENT_CONTENT_TYPE,
                InputArgument::REQUIRED,
                'Content type name to import into'
            )
            ->addArgument(
                self::ARGUMENT_ARCHIVE,
                InputArgument::REQUIRED,
                'The archive (zip file or directory) containing the json files'
            )
            ->addOption(
                self::OPTION_BULK_SIZE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                $this->defaultBulkSize
            )
            ->addOption(
                self::OPTION_RAW,
                null,
                InputOption::VALUE_NONE,
                'The content will be imported as is. Without any field validation, data stripping or field protection'
            )
            ->addOption(
                self::OPTION_DONT_SIGN_DATA,
                null,
                InputOption::VALUE_NONE,
                'The content will not be signed during the import process'
            )
            ->addOption(
                self::OPTION_FORCE,
                null,
                InputOption::VALUE_NONE,
                'Also treat document in draft mode'
            )
            ->addOption(
                self::OPTION_DONT_FINALIZE,
                null,
                InputOption::VALUE_NONE,
                'Don\'t finalize document'
            )
            ->addOption(
                self::OPTION_BUSINESS_KEY,
                null,
                InputOption::VALUE_NONE,
                'Try to identify documents by their business keys'
            );
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $this->io) {
            throw new \RuntimeException('Unexpected null SymfonyStyle');
        }
        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        $archiveFilename = $input->getArgument(self::ARGUMENT_ARCHIVE);
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Content Type name as to be a string');
        }
        if (!\is_string($archiveFilename)) {
            throw new \RuntimeException('Archive Filename as to be a string');
        }

        $this->io->title('Make documents');
        $this->io->section('Checking input');

        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException(\sprintf('Content type %s not found', $contentTypeName));
        }

        if ($contentType->getDirty()) {
            throw new \RuntimeException(\sprintf('Content type %s is dirty. Please clean it first', $contentTypeName));
        }
        $this->contentType = $contentType;

        if (!\file_exists($archiveFilename)) {
            throw new \RuntimeException(\sprintf('Archive file %s does not exist', $archiveFilename));
        }
        $this->archiveFilename = $archiveFilename;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->io) {
            throw new \RuntimeException('Unexpected null SymfonyStyle');
        }
        if (null === $this->contentType) {
            throw new \RuntimeException('Unexpected null contentType');
        }
        if (null === $this->archiveFilename) {
            throw new \RuntimeException('Unexpected null archiveFilename');
        }
        $bulkSize = \intval($input->getOption(self::OPTION_BULK_SIZE));
        if ($bulkSize <= 0) {
            throw new \RuntimeException('Invalid bulk size');
        }
        $rawImport = $input->getOption(self::OPTION_RAW);
        if (!\is_bool($rawImport)) {
            throw new \RuntimeException('Unexpected force option');
        }
        $dontSignData = $input->getOption(self::OPTION_DONT_SIGN_DATA);
        if (!\is_bool($dontSignData)) {
            throw new \RuntimeException('Unexpected force option');
        }
        $force = $input->getOption(self::OPTION_FORCE);
        if (!\is_bool($force)) {
            throw new \RuntimeException('Unexpected force option');
        }
        $dontFinalize = $input->getOption(self::OPTION_DONT_FINALIZE);
        if (!\is_bool($dontFinalize)) {
            throw new \RuntimeException('Unexpected force option');
        }
        $replaceBusinessKey = $input->getOption(self::OPTION_BUSINESS_KEY);
        if (!\is_bool($replaceBusinessKey)) {
            throw new \RuntimeException('Unexpected force option');
        }

        $signData = !$dontSignData;
        $finalize = !$dontFinalize;

        $this->io->section(\sprintf('Start importing %s from %s', $this->contentType->getPluralName(), $this->archiveFilename));
        $directory = TempDirectory::createFromZipArchive($this->archiveFilename);

        $finder = new Finder();
        $finder->files()->in($directory->path)->name('*.json');
        $progress = $this->io->createProgressBar($finder->count());
        $progress->start();
        $importerContext = $this->documentService->initDocumentImporterContext($this->contentType, 'SYSTEM_IMPORT', $rawImport, $signData, true, $bulkSize, $finalize, $force);

        $loopIndex = 0;
        foreach ($finder as $file) {
            $content = \file_get_contents($file);
            if (false === $content) {
                $progress->advance();
                continue;
            }
            $rawData = Json::decode($content);
            $ouuid = \basename($file->getFilename(), '.json');
            if ($replaceBusinessKey) {
                $dataLink = $this->dataService->getDataLink($this->contentType->getName(), $ouuid);
                if ($dataLink === $ouuid) {
                    // TODO: Should test if a document already exist with the business key as ouuid
                    // TODO: Check that a document doesn't already exist with the hash value (it has to be new)
                    // TODO: meaby use a UUID generator? Or allow elasticsearch to generate one
                    $ouuid = \sha1($dataLink);
                } else {
                    $dataLink = \explode(':', $dataLink);
                    $ouuid = \array_pop($dataLink);
                }
            }

            $document = $this->dataService->hitFromBusinessIdToDataLink($this->contentType, $ouuid, $rawData);

            try {
                $this->documentService->importDocument($importerContext, $document->getOuuid(), $document->getSource());
            } catch (NotLockedException|CantBeFinalizedException $e) {
                $this->io->error($e);
            }

            ++$loopIndex;
            if (0 == $loopIndex % $bulkSize) {
                $this->documentService->flushAndSend($importerContext);
                $loopIndex = 0;
            }
            $progress->advance();
        }
        $this->documentService->flushAndSend($importerContext);
        $progress->finish();
        $this->io->writeln('');
        $this->io->writeln('Import done');

        return 0;
    }
}
