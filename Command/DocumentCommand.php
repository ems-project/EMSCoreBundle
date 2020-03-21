<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Helper\Archive;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\DocumentService;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class DocumentCommand extends Command
{
    protected static $defaultName = 'ems:make:document';

    /** @var DocumentService */
    private $documentService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var DataService */
    private $dataService;
    /** @var Logger */
    private $logger;
    /** @var Client */
    private $client;
    /** @var SymfonyStyle */
    private $io;
    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $archiveFilename;

    const ARGUMENT_CONTENTTYPE = 'contentTypeName';
    const ARGUMENT_ARCHIVE = 'archive';

    public function __construct(Logger $logger, Client $client, ContentTypeService $contentTypeService, DocumentService $documentService, DataService $dataService)
    {
        $this->contentTypeService = $contentTypeService;
        $this->documentService = $documentService;
        $this->dataService = $dataService;
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->client = $client;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setDescription('Import json files from a zip file as content type\'s documents')
            ->addArgument(
                self::ARGUMENT_CONTENTTYPE,
                InputArgument::REQUIRED,
                'Content type name to import into'
            )
            ->addArgument(
                self::ARGUMENT_ARCHIVE,
                InputArgument::REQUIRED,
                'The archive (zip file or directory) containing the json files'
            )
            ->addOption(
                'bulkSize',
                null,
                InputOption::VALUE_OPTIONAL,
                'Size of the elasticsearch bulk request',
                500
            )
            ->addOption(
                'raw',
                null,
                InputOption::VALUE_NONE,
                'The content will be imported as is. Without any field validation, data stripping or field protection'
            )
            ->addOption(
                'dont-sign-data',
                null,
                InputOption::VALUE_NONE,
                'The content will not be signed during the import process'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Also treat document in draft mode'
            )
            ->addOption(
                'dont-finalize',
                null,
                InputOption::VALUE_NONE,
                'Don\'t finalize document'
            )
            ->addOption(
                'businessKey',
                null,
                InputOption::VALUE_NONE,
                'Try to identify documents by their business keys'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENTTYPE);
        $archiveFilename = $input->getArgument(self::ARGUMENT_ARCHIVE);

        $this->io->title('Make documents');
        $this->io->section('Checking input');


        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            $this->io->error(sprintf('Content type %s not found', $contentTypeName));
            return -1;
        }

        if ($contentType->getDirty()) {
            $this->io->error(sprintf('Content type %s is dirty. Please clean it first', $contentTypeName));
            return -1;
        }
        $this->contentType = $contentType;

        if (!file_exists($archiveFilename)) {
            $this->io->error(sprintf('Archive file %s does not exist', $archiveFilename));
            return -1;
        }
        $this->archiveFilename = $archiveFilename;

        return 0;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $options = array_values($input->getOptions());
        list($bulkSize, $rawImport, $dontSignData, $force, $dontFinalize, $replaceBusinessKey) = $options;

        $signData = !$dontSignData;
        $finalize = !$dontFinalize;

        $this->io->section(sprintf('Start importing %s from %s', $this->contentType->getPluralName(), $this->archiveFilename));

        $archive = new Archive();
        $directory = $archive->extractToDirectory($this->archiveFilename);

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.json');
        $progress = $this->io->createProgressBar($finder->count());
        $progress->start();
        $importerContext = $this->documentService->initDocumentImporterContext($this->contentType, 'SYSTEM_IMPORT', $rawImport, $signData, true, $bulkSize, $finalize, $force);

        $loopIndex = 0;
        foreach ($finder as $file) {
            $rawData = json_decode(file_get_contents($file), true);
            $ouuid = basename($file->getFilename(), '.json');
            if ($replaceBusinessKey) {
                $dataLink = $this->dataService->getDataLink($this->contentType->getName(), $ouuid);
                if ($dataLink === $ouuid) {
                    //TODO: Should test if a document already exist with the business key as ouuid
                    //TODO: Check that a document doesn't already exist with the hash value (it has to be new)
                    //TODO: meaby use a UUID generator? Or allow elasticsearch to generate one
                    $ouuid = sha1($dataLink);
                } else {
                    $dataLink = explode(':', $dataLink);
                    $ouuid = array_pop($dataLink);
                }
            }

            $document = $this->dataService->hitFromBusinessIdToDataLink($this->contentType, $ouuid, $rawData);

            try {
                $this->documentService->importDocument($importerContext, $document->getOuuid(), $document->getSource());
            } catch (NotLockedException $e) {
                $this->io->error($e);
            } catch (CantBeFinalizedException $e) {
                $this->io->error($e);
            }

            ++$loopIndex;
            if ($loopIndex % $bulkSize == 0) {
                $this->documentService->flushAndSend($importerContext);
                $loopIndex = 0;
            }
            $progress->advance();
        }
        $this->documentService->flushAndSend($importerContext);
        $progress->finish();
        $this->io->writeln("");
        $this->io->writeln("Import done");
    }
}
