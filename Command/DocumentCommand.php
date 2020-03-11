<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\CantBeFinalizedException;
use EMS\CoreBundle\Exception\NotLockedException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ImportService;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class DocumentCommand extends Command
{
    protected static $defaultName = 'ems:make:document';

    /** @var ImportService */
    private $importService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var DataService */
    private $dataService;
    /** @var Logger */
    private $logger;
    /** @var Client */
    private $client;

    public function __construct(Logger $logger, Client $client, ContentTypeService $contentTypeService, ImportService $importService, DataService $dataService)
    {
        $this->contentTypeService = $contentTypeService;
        $this->importService = $importService;
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
                'contentTypeName',
                InputArgument::REQUIRED,
                'Content type name to import into'
            )
            ->addArgument(
                'archive',
                InputArgument::REQUIRED,
                'Zip file containing the json files'
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($contentTypeName, $archiveFilename) = $input->getArguments();
        list($bulkSize, $rawImport, $dontSignData, $force, $dontFinalize, $replaceBusinessKey) = $input->getOptions();

        $signData = !$dontSignData;
        $finalize = !$dontFinalize;


        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            $output->writeln(sprintf('<error>Content type %s not found</error>', $contentTypeName));
            return -1;
        }

        if ($contentType->getDirty()) {
            $output->writeln(sprintf('<error>Content type %s is dirty. Please clean it first.</error>', $contentTypeName));
            return -1;
        }

        if (!file_exists($archiveFilename)) {
            $output->writeln(sprintf('<error>Archive file %s does not exist.</error>', $archiveFilename));
            return -1;
        }
        
        $output->writeln(sprintf('Start importing %s from %s', $contentType->getPluralName(), $archiveFilename));

        $zip = new \ZipArchive();
        if ($zip->open($archiveFilename) !== true) {
            $output->writeln(sprintf('<error>Archive file %s can not be open.</error>', $archiveFilename));
            return -1;
        }

        $workingDirectory = tempnam(sys_get_temp_dir(), 'ImportCommand');
        $filesystem = new Filesystem();
        $filesystem->remove($workingDirectory);
        $filesystem->mkdir($workingDirectory);

        $zip->extractTo($workingDirectory);
        $zip->close();

        $finder = new Finder();
        $finder->files()->in($workingDirectory)->name('*.json');
        $progress = new ProgressBar($output, $finder->count());
        $progress->start();
        $importer = $this->importService->initDocumentImporter($contentType, 'SYSTEM_IMPORT', $rawImport, $signData, true, $bulkSize, $finalize, $force);

        $loopIndex = 0;
        foreach ($finder as $file) {
            $rawData = json_decode(file_get_contents($file), true);
            $ouuid = basename($file->getFilename(), '.json');
            if ($replaceBusinessKey) {
                $dataLink = $this->dataService->getDataLink($contentType->getName(), $ouuid);
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

            $document = $this->dataService->hitFromBusinessIdToDataLink($contentType, $ouuid, $rawData);

            try {
                $importer->importDocument($document->getOuuid(), $document->getSource());
            } catch (NotLockedException $e) {
                $output->writeln("<error>'.$e.'</error>");
            } catch (CantBeFinalizedException $e) {
                $output->writeln("<error>'.$e.'</error>");
            }

            ++$loopIndex;
            if ($loopIndex % $bulkSize == 0) {
                $importer->flushAndSend();
                $loopIndex = 0;
            }
            $progress->advance();
        }
        $importer->flushAndSend();
        $progress->finish();
        $output->writeln("");
        $output->writeln("Import done");
    }
}
