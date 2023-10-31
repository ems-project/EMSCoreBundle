<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use EMS\Helpers\Standard\Json;
use EMS\Xliff\Xliff\Extractor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ExtractCommand extends AbstractCommand
{
    private string $sourceLocale;
    private Environment $sourceEnvironment;
    private string $targetLocale;
    private ?Environment $targetEnvironment = null;
    /** @var string[] */
    private array $fields;
    /**
     * @var array<mixed>
     */
    private array $searchQuery;
    private int $bulkSize;

    public const ARGUMENT_SOURCE_ENVIRONMENT = 'source-environment';
    public const ARGUMENT_SEARCH_QUERY = 'search-query';
    public const ARGUMENT_SOURCE_LOCALE = 'source-locale';
    public const ARGUMENT_TARGET_LOCALE = 'target-locale';
    public const OPTION_TARGET_ENVIRONMENT = 'target-environment';
    public const ARGUMENT_FIELDS = 'fields';
    public const OPTION_BULK_SIZE = 'bulk-size';
    public const OPTION_XLIFF_VERSION = 'xliff-version';
    public const OPTION_FILENAME = 'filename';
    public const OPTION_BASE_URL = 'base-url';
    public const OPTION_TRANSLATION_FIELD = 'translation-field';
    public const OPTION_LOCALE_FIELD = 'locale-field';
    public const OPTION_ENCODING = 'encoding';
    public const OPTION_WITH_BASELINE = 'with-baseline';
    public const OPTION_MAIL_SUBJECT = 'mail-subject';
    public const OPTION_MAIL_TO = 'mail-to';
    public const OPTION_MAIL_CC = 'mail-cc';
    private const MAIL_TEMPLATE = '@EMSCore/email/xliff/extract.email.html.twig';

    protected static $defaultName = Commands::XLIFF_EXTRACT;
    private string $xliffFilename;
    private ?string $baseUrl = null;
    private string $xliffVersion;
    private ?string $translationField;
    private ?string $localeField;
    private string $encoding;
    private bool $withBaseline;
    private string $mailSubject;
    private ?string $mailTo;
    private ?string $mailCC;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly EnvironmentService $environmentService,
        private readonly ElasticaService $elasticaService,
        private readonly XliffService $xliffService,
        private readonly AssetRuntime $assetRuntime,
        private readonly MailerService $mailerService,
        private readonly int $defaultBulkSize,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_SOURCE_ENVIRONMENT, InputArgument::REQUIRED, 'Environment with the source documents')
            ->addArgument(self::ARGUMENT_SEARCH_QUERY, InputArgument::REQUIRED, 'Query used to find elasticsearch records to extract from the source environment')
            ->addArgument(self::ARGUMENT_SOURCE_LOCALE, InputArgument::REQUIRED, 'Source locale')
            ->addArgument(self::ARGUMENT_TARGET_LOCALE, InputArgument::REQUIRED, 'Target locale')
            ->addArgument(self::ARGUMENT_FIELDS, InputArgument::IS_ARRAY, 'List of content type\s fields to extract. Use the pattern %locale% if required. Use the `.` to separate nested fields from their parent. Use `json:` `id_key:` and/or `base64;` to decode a field. You can also use `*` as wild char and `|` to list children fields  E.g. `%locale%.json:id_key:content.object.title|content` or `[%locale%][json:id_key:content][object][title|content]`')
            ->addOption(self::OPTION_BULK_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request', $this->defaultBulkSize)
            ->addOption(self::OPTION_TARGET_ENVIRONMENT, null, InputOption::VALUE_OPTIONAL, 'Environment with the target documents')
            ->addOption(self::OPTION_XLIFF_VERSION, null, InputOption::VALUE_OPTIONAL, 'XLIFF format version: '.\implode(' ', Extractor::XLIFF_VERSIONS), Extractor::XLIFF_1_2)
            ->addOption(self::OPTION_FILENAME, null, InputOption::VALUE_OPTIONAL, 'Generate the XLIFF specified file')
            ->addOption(self::OPTION_BASE_URL, null, InputOption::VALUE_OPTIONAL, 'Base url, in order to generate a download link to the XLIFF file')
            ->addOption(self::OPTION_LOCALE_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the locale', null)
            ->addOption(self::OPTION_ENCODING, null, InputOption::VALUE_OPTIONAL, 'Encoding used to generate the XLIFF file', 'UTF-8')
            ->addOption(self::OPTION_TRANSLATION_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the translation field', null)
            ->addOption(self::OPTION_WITH_BASELINE, null, InputOption::VALUE_NONE, 'The baseline has been checked and can be used to flag field as final')
            ->addOption(self::OPTION_MAIL_SUBJECT, null, InputOption::VALUE_OPTIONAL, 'Mail subject', 'A new XLIFF has been generated')
            ->addOption(self::OPTION_MAIL_TO, null, InputOption::VALUE_OPTIONAL, 'A comma seperated list of emails where to send the XLIFF')
            ->addOption(self::OPTION_MAIL_CC, null, InputOption::VALUE_OPTIONAL, 'A comma seperated list of emails where to send, in carbon copy, the XLIFF');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Extract');

        $this->bulkSize = $this->getOptionInt(self::OPTION_BULK_SIZE);
        $this->searchQuery = Json::decode($this->getArgumentString(self::ARGUMENT_SEARCH_QUERY));
        $this->sourceLocale = $this->getArgumentString(self::ARGUMENT_SOURCE_LOCALE);
        $this->targetLocale = $this->getArgumentString(self::ARGUMENT_TARGET_LOCALE);
        $this->fields = $this->getArgumentStringArray(self::ARGUMENT_FIELDS);
        $this->sourceEnvironment = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_SOURCE_ENVIRONMENT));
        $this->targetEnvironment = $this->getOptionStringNull(self::OPTION_TARGET_ENVIRONMENT) ? $this->environmentService->giveByName($this->getOptionString(self::OPTION_TARGET_ENVIRONMENT)) : $this->sourceEnvironment;
        $xliffFilename = $this->getOptionStringNull(self::OPTION_FILENAME);
        $this->xliffFilename = $xliffFilename ?? \tempnam(\sys_get_temp_dir(), 'ems-extract-').'.xlf';
        $this->baseUrl = $this->getOptionStringNull(self::OPTION_BASE_URL);
        $this->xliffVersion = $this->getOptionString(self::OPTION_XLIFF_VERSION);
        $this->translationField = $this->getOptionStringNull(self::OPTION_TRANSLATION_FIELD);
        $this->localeField = $this->getOptionStringNull(self::OPTION_LOCALE_FIELD);
        $this->encoding = $this->getOptionString(self::OPTION_ENCODING);
        $this->withBaseline = $this->getOptionBool(self::OPTION_WITH_BASELINE);
        $this->mailSubject = $this->getOptionString(self::OPTION_MAIL_SUBJECT);
        $this->mailTo = $this->getOptionStringNull(self::OPTION_MAIL_TO);
        $this->mailCC = $this->getOptionStringNull(self::OPTION_MAIL_CC);

        if (null === $this->translationField && $this->translationField !== $this->localeField) {
            throw new \RuntimeException(\sprintf('Both %s and %s options must be defined or not defined at all (fields defined with %%locale%% placeholder)', self::OPTION_TRANSLATION_FIELD, self::OPTION_LOCALE_FIELD));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF export for fields: %s', \implode(' ', $this->fields)),
        ]);

        $search = new Search([$this->sourceEnvironment->getAlias()], $this->searchQuery);
        $search->setSources(EMSSource::REQUIRED_FIELDS);
        $search->setSize($this->bulkSize);
        $scroll = $this->elasticaService->scroll($search);
        $total = $this->elasticaService->count($search);
        $this->io->progressStart($total);

        $extractor = new Extractor($this->sourceLocale, $this->targetLocale, $this->xliffVersion);

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                $source = Document::fromResult($result);
                try {
                    $contentType = $this->contentTypeService->giveByName($source->getContentType());
                    $this->xliffService->extract($contentType, $source, $extractor, $this->fields, $this->sourceEnvironment, $this->targetEnvironment, $this->targetLocale, $this->localeField, $this->translationField, $this->withBaseline);
                } catch (\Throwable $e) {
                    $this->io->warning($e->getMessage());
                }
                $this->io->progressAdvance();
            }
        }
        $this->io->progressFinish();

        if (!$extractor->saveXML($this->xliffFilename, $this->encoding)) {
            throw new \RuntimeException(\sprintf('Unexpected error while saving the XLIFF to the file %s', $this->xliffFilename));
        }
        $this->sendEmail($this->xliffFilename);

        if (null !== $this->baseUrl) {
            $this->xliffFilename = $this->baseUrl.$this->assetRuntime->assetPath(
                [
                    EmsFields::CONTENT_FILE_NAME_FIELD_ => \basename($this->xliffFilename),
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

    private function sendEmail(string $xliffFilename): void
    {
        if (null === $this->mailTo && null === $this->mailCC) {
            return;
        }
        $mailTemplate = $this->mailerService->makeMailTemplate(self::MAIL_TEMPLATE);
        $mailTemplate->setSubjectText($this->mailSubject);
        if (null !== $this->mailTo) {
            $split = \explode(',', $this->mailTo);
            foreach ($split as $email) {
                $mailTemplate->addTo($email);
            }
        }
        if (null !== $this->mailCC) {
            $split = \explode(',', $this->mailCC);
            foreach ($split as $email) {
                $mailTemplate->addCc($email);
            }
        }
        $mailTemplate->setBodyBlock('xliff_extracted', [
            'source' => $this->sourceLocale,
            'target' => $this->targetLocale,
            'query' => $this->searchQuery,
            'fields' => $this->fields,
        ]);
        $mailTemplate->addAttachment($xliffFilename);

        $this->mailerService->sendMailTemplate($mailTemplate);
    }
}
