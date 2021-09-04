<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ExtractCommand extends AbstractCommand
{
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;

    private ContentType $sourceContentType;
    private string $searchQuery;
    private int $defaultBulkSize;
    private int $bulkSize;
    private string $sourceLocale;
    private string $targetLocale;
    private ContentType $targetContentType;
    private Environment $sourceEnvironment;
    private Environment $targetEnvironment;
    /** @var string[] */
    private array $fields;
    /**
     * @var array<mixed>
     */
    private array $query;

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

    protected static $defaultName = Commands::XLIFF_EXTRACTOR;

    public function __construct(
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        int $defaultBulkSize
    ) {
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->defaultBulkSize = $defaultBulkSize;
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
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Extract');

        $this->bulkSize = $this->getOptionInt(self::OPTION_BULK_SIZE);
        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
        $this->sourceContentType = $this->contentTypeService->giveByName($this->getArgumentString(self::ARGUMENT_SOURCE_CONTENT_TYPE));
        $this->sourceLocale = $this->getArgumentString(self::ARGUMENT_SOURCE_LOCALE);
        $this->targetLocale = $this->getArgumentString(self::ARGUMENT_TARGET_LOCALE);
        $this->query = Json::decode($this->getOptionString(self::OPTION_SEARCH_QUERY));
        $this->fields = $this->getArgumentStringArray(self::ARGUMENT_FIELDS);
        $this->targetContentType = $this->getOptionStringNull(self::OPTION_TARGET_CONTENT_TYPE) ? $this->contentTypeService->giveByName($this->getOptionString(self::OPTION_TARGET_CONTENT_TYPE)) : $this->sourceContentType;
        $this->sourceEnvironment = $this->getOptionStringNull(self::OPTION_SOURCE_ENVIRONMENT) ? $this->environmentService->giveByName($this->getOptionString(self::OPTION_SOURCE_ENVIRONMENT)) : $this->sourceContentType->giveEnvironment();
        $this->targetEnvironment = $this->getOptionStringNull(self::OPTION_TARGET_ENVIRONMENT) ? $this->environmentService->giveByName($this->getOptionString(self::OPTION_TARGET_ENVIRONMENT)) : $this->targetContentType->giveEnvironment();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF export of %s for %s fields from %s', $this->sourceContentType->getPluralName(), $this->sourceLocale, $this->sourceEnvironment->getName()),
            \sprintf('In order to insert them as %s for %s fields to %s', $this->targetContentType->getPluralName(), $this->targetLocale, $this->targetEnvironment->getName()),
            \sprintf('For fields: %s', \implode(' ', $this->fields)),
        ]);

        return self::EXECUTE_SUCCESS;
    }
}
