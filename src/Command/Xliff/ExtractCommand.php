<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use EMS\CoreBundle\Helper\Xliff\InsertionRevision;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ExtractCommand extends AbstractCommand
{
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;
    private ElasticaService $elasticaService;
    private XliffService $xliffService;
    private int $defaultBulkSize;

    private ContentType $sourceContentType;
    private string $sourceLocale;
    private Environment $sourceEnvironment;
    private ContentType $targetContentType;
    private string $targetLocale;
    private Environment $targetEnvironment;
    /** @var string[] */
    private array $fields;
    /**
     * @var array<mixed>
     */
    private array $searchQuery;
    private int $bulkSize;

    public const ARGUMENT_SOURCE_CONTENT_TYPE = 'source-content-type';
    public const ARGUMENT_FIELDS = 'fields';
    public const ARGUMENT_SOURCE_LOCALE = 'source-locale';
    public const ARGUMENT_TARGET_LOCALE = 'target-locale';
    public const OPTION_SOURCE_DOCUMENT_FIELD = 'source-field';
    public const OPTION_BULK_SIZE = 'bulk-size';
    public const OPTION_SEARCH_QUERY = 'search-query';
    public const OPTION_SOURCE_ENVIRONMENT = 'source-environment';
    public const OPTION_TARGET_ENVIRONMENT = 'target-environment';
    public const OPTION_TARGET_CONTENT_TYPE = 'target-content-type';
    public const OPTION_XLIFF_VERSION = 'xliff-version';
    public const OPTION_FILENAME = 'filename';
    public const OPTION_BASE_URL = 'base-url';

    protected static $defaultName = Commands::XLIFF_EXTRACTOR;
    private ?string $sourceDocumentField;
    private string $xliffFilename;
    private ?string $baseUrl;
    private string $xliffVersion;
    private AssetRuntime $assetRuntime;

    public function __construct(
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        ElasticaService $elasticaService,
        XliffService $xliffService,
        AssetRuntime $assetRuntime,
        int $defaultBulkSize
    ) {
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->elasticaService = $elasticaService;
        $this->defaultBulkSize = $defaultBulkSize;
        $this->xliffService = $xliffService;
        $this->assetRuntime = $assetRuntime;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_SOURCE_CONTENT_TYPE, InputArgument::REQUIRED, 'Source ContentType name')
            ->addArgument(self::ARGUMENT_SOURCE_LOCALE, InputArgument::REQUIRED, 'Source locale')
            ->addArgument(self::ARGUMENT_TARGET_LOCALE, InputArgument::REQUIRED, 'Target locale')
            ->addArgument(self::ARGUMENT_FIELDS, InputArgument::IS_ARRAY, 'List of content type\s fields to extract. Use the pattern %locale% if required')
            ->addOption(self::OPTION_SOURCE_DOCUMENT_FIELD, null, InputOption::VALUE_REQUIRED, 'Field with a link to the source document. If not defined we assume that document contains fields for all locales')
            ->addOption(self::OPTION_BULK_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request', $this->defaultBulkSize)
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to transform', '{}')
            ->addOption(self::OPTION_SOURCE_ENVIRONMENT, null, InputOption::VALUE_OPTIONAL, 'Environment with the source documents')
            ->addOption(self::OPTION_TARGET_ENVIRONMENT, null, InputOption::VALUE_OPTIONAL, 'Environment with the target documents')
            ->addOption(self::OPTION_TARGET_CONTENT_TYPE, null, InputOption::VALUE_OPTIONAL, 'Target ContentType name')
            ->addOption(self::OPTION_XLIFF_VERSION, null, InputOption::VALUE_OPTIONAL, 'XLIFF format version: '.\implode(' ', Extractor::XLIFF_VERSIONS), Extractor::XLIFF_1_2)
            ->addOption(self::OPTION_FILENAME, null, InputOption::VALUE_OPTIONAL, 'Generate the XLIFF specified file')
            ->addOption(self::OPTION_BASE_URL, null, InputOption::VALUE_OPTIONAL, 'Base url, in order to generate a download link to the XLIFF file');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Extract');

        $this->bulkSize = $this->getOptionInt(self::OPTION_BULK_SIZE);
        $this->searchQuery = Json::decode($this->getOptionString(self::OPTION_SEARCH_QUERY));
        $this->sourceContentType = $this->contentTypeService->giveByName($this->getArgumentString(self::ARGUMENT_SOURCE_CONTENT_TYPE));
        $this->sourceLocale = $this->getArgumentString(self::ARGUMENT_SOURCE_LOCALE);
        $this->targetLocale = $this->getArgumentString(self::ARGUMENT_TARGET_LOCALE);
        $this->fields = $this->getArgumentStringArray(self::ARGUMENT_FIELDS);
        $this->targetContentType = $this->getOptionStringNull(self::OPTION_TARGET_CONTENT_TYPE) ? $this->contentTypeService->giveByName($this->getOptionString(self::OPTION_TARGET_CONTENT_TYPE)) : $this->sourceContentType;
        $this->sourceEnvironment = $this->getOptionStringNull(self::OPTION_SOURCE_ENVIRONMENT) ? $this->environmentService->giveByName($this->getOptionString(self::OPTION_SOURCE_ENVIRONMENT)) : $this->sourceContentType->giveEnvironment();
        $this->targetEnvironment = $this->getOptionStringNull(self::OPTION_TARGET_ENVIRONMENT) ? $this->environmentService->giveByName($this->getOptionString(self::OPTION_TARGET_ENVIRONMENT)) : $this->targetContentType->giveEnvironment();
        $this->sourceDocumentField = $this->getOptionStringNull(self::OPTION_SOURCE_DOCUMENT_FIELD);
        $xliffFilename = $this->getOptionStringNull(self::OPTION_FILENAME);
        $this->xliffFilename = $xliffFilename ?? \tempnam(\sys_get_temp_dir(), 'ems-extract-').'.xlf';
        $this->baseUrl = $this->getOptionStringNull(self::OPTION_BASE_URL);
        $this->xliffVersion = $this->getOptionString(self::OPTION_XLIFF_VERSION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF export of %s for %s fields from %s', $this->sourceContentType->getPluralName(), $this->sourceLocale, $this->sourceEnvironment->getName()),
            \sprintf('In order to insert them as %s for %s fields to %s', $this->targetContentType->getPluralName(), $this->targetLocale, $this->targetEnvironment->getName()),
            \sprintf('For fields: %s', \implode(' ', $this->fields)),
        ]);

        $search = $this->elasticaService->convertElasticsearchBody([$this->sourceEnvironment->getAlias()], [$this->sourceContentType->getName()], $this->searchQuery);
        $searchSource = $this->getSources();
        $search->setSize($this->bulkSize);
        $search->setSources($searchSource);
        $scroll = $this->elasticaService->scroll($search);
        $total = $this->elasticaService->count($search);
        $this->io->progressStart($total);

        $extractor = new Extractor($this->sourceLocale, $this->targetLocale, $this->xliffVersion);

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if (false === $result) {
                    continue;
                }
                $source = Document::fromResult($result);
                try {
                    $this->xliffService->extract($source, $extractor, $this->fields, $this->targetContentType, $this->targetEnvironment, $this->sourceDocumentField);
                } catch (XliffException $e) {
                    $this->io->warning($e->getMessage());
                }
                $this->io->progressAdvance();
            }
        }
        $this->io->progressFinish();

        if (!$extractor->saveXML($this->xliffFilename)) {
            throw new \RuntimeException(\sprintf('Unexpected error while saving the XLIFF to the file %s', $this->xliffFilename));
        }

        if (null !== $this->baseUrl) {
            $this->xliffFilename = $this->baseUrl.$this->assetRuntime->assetPath(
                    [
                        EmsFields::CONTENT_FILE_NAME_FIELD_ => 'extract.xlf',
                        EmsFields::CONTENT_FILE_HASH_FIELD_ => \sha1_file($this->xliffFilename),
                    ],
                    [
                        EmsFields::ASSET_CONFIG_FILE_NAMES => [$this->xliffFilename],
                    ],
                    'ems_asset',
                    EmsFields::CONTENT_FILE_HASH_FIELD,
                    EmsFields::CONTENT_FILE_NAME_FIELD,
                    EmsFields::CONTENT_MIME_TYPE_FIELD,
                    UrlGeneratorInterface::ABSOLUTE_PATH
                );
        }

        $output->writeln('');
        $output->writeln('XLIFF file: '.$this->xliffFilename);

        return self::EXECUTE_SUCCESS;
    }

    /**
     * @return string[]
     */
    private function getSources(): array
    {
        $sources = [];
        if (null !== $this->sourceDocumentField) {
            $sources[] = $this->sourceDocumentField;
        }
        foreach ($this->fields as $field) {
            if (false === \strpos($field, InsertionRevision::LOCALE_PLACE_HOLDER)) {
                $sources[] = $field;
                continue;
            }
            foreach ([$this->sourceLocale, $this->targetLocale] as $locale) {
                $localized = \str_replace(InsertionRevision::LOCALE_PLACE_HOLDER, $locale, $field);
                if (!\is_string($localized)) {
                    throw new \RuntimeException('Unexpected str_replace error');
                }
                $sources[] = $localized;
            }
        }

        return $sources;
    }
}
