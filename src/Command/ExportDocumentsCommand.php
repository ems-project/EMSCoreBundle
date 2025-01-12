<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\TemplateService;
use EMS\Helpers\File\TempFile;
use EMS\Helpers\Html\MimeTypes;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\Error;

#[AsCommand(
    name: Commands::CONTENT_TYPE_EXPORT,
    description: 'Export a search result of a content type to a specific format.',
    hidden: false,
    aliases: ['ems:contenttype:export']
)]
class ExportDocumentsCommand extends AbstractCommand
{
    private const string ARGUMENT_QUERY = 'query';
    private const string OPTION_ENVIRONMENT = 'environment';
    private const string OPTION_WITH_BUSINESS_ID = 'withBusinessId';
    private const string OPTION_SCROLL_SIZE = 'scrollSize';
    private const string OPTION_SCROLL_TIMEOUT = 'scrollTimeout';
    private const string OPTION_BASE_URL = 'baseUrl';
    public const ARGUMENT_CONTENT_TYPE_NAME = 'contentTypeName';
    private const string ARGUMENT_FORMAT = 'format';
    final public const string ARGUMENT_OUTPUT_FILE = 'outputFile';
    private string $contentTypeName;
    private string $format;
    private int $scrollSize;
    private string $scrollTimeout;
    private bool $withBusinessId;
    private ?string $baseUrl = null;
    private ?string $environmentName = null;
    /**
     * @var mixed[]
     */
    private array $query;
    private ?string $zipFilename = null;

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly TemplateService $templateService,
        protected readonly DataService $dataService,
        protected readonly ContentTypeService $contentTypeService,
        protected readonly EnvironmentService $environmentService,
        protected readonly AssetRuntime $runtime,
        private readonly ElasticaService $elasticaService,
        private readonly StorageManager $storageManager,
        protected readonly string $instanceId)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE_NAME, InputArgument::REQUIRED, 'The document\'s content type name to export')
            ->addArgument(self::ARGUMENT_FORMAT, InputArgument::OPTIONAL, \sprintf('The format of the output: %s or the name of the content type\'s action', \implode(', ', TemplateService::EXPORT_FORMATS)), 'json')
            ->addArgument(self::ARGUMENT_QUERY, InputArgument::OPTIONAL, 'The query to run', '{}')
            ->addArgument(self::ARGUMENT_OUTPUT_FILE, InputArgument::OPTIONAL, 'The zip output file', null)
            ->addOption(self::OPTION_ENVIRONMENT, null, InputArgument::OPTIONAL, 'The environment to use for the query, it will use the default environment if not defined')
            ->addOption(self::OPTION_WITH_BUSINESS_ID, null, InputOption::VALUE_NONE, 'Replace internal OUUIDs by business values')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputArgument::OPTIONAL, 'Size of the elasticsearch scroll request', '100')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputArgument::OPTIONAL, 'Time to migrate "scrollSize" items i.e. 30s or 2m', '1m')
            ->addOption(self::OPTION_BASE_URL, null, InputArgument::OPTIONAL, 'Base url of the application (in order to generate a link)', null);
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->contentTypeName = $this->getArgumentString(self::ARGUMENT_CONTENT_TYPE_NAME);
        $this->format = $this->getArgumentString(self::ARGUMENT_FORMAT);
        $this->query = Json::decode($this->getArgumentString(self::ARGUMENT_QUERY));
        $this->zipFilename = $this->getArgumentStringNull(self::ARGUMENT_OUTPUT_FILE);
        $this->environmentName = $this->getOptionStringNull(self::OPTION_ENVIRONMENT);
        $this->withBusinessId = $this->getOptionBool(self::OPTION_WITH_BUSINESS_ID);
        $this->scrollSize = $this->getOptionInt(self::OPTION_SCROLL_SIZE);
        $this->scrollTimeout = $this->getOptionString(self::OPTION_SCROLL_TIMEOUT);
        $this->baseUrl = $this->getOptionStringNull(self::OPTION_BASE_URL);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contentType = $this->contentTypeService->giveByName($this->contentTypeName);
        if (null === $this->environmentName) {
            $environment = $contentType->giveEnvironment();
            $index = $environment->getAlias();
            $this->environmentName = $environment->getName();
        } else {
            $environment = $this->environmentService->giveByName($this->environmentName);
            $index = $environment->getAlias();
        }

        unset($this->query['sort']);
        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $index,
            'type' => $this->contentTypeName,
            'size' => $this->scrollSize,
            'body' => $this->query,
        ]);

        $scroll = $this->elasticaService->scroll($search, $this->scrollTimeout);
        $total = $this->elasticaService->count($search);

        $this->io->progressStart($total);
        $outZipPath = $this->zipFilename;
        if (null === $outZipPath) {
            $tempFile = TempFile::create();
            $outZipPath = $tempFile->path;
        }
        $zip = new \ZipArchive();
        $zip->open($outZipPath, \ZipArchive::CREATE);
        $extension = '';
        if (!\in_array($this->format, TemplateService::EXPORT_FORMATS)) {
            $this->templateService->init($this->format, $contentType);
            $useTemplate = true;
            $accumulateInOneFile = $this->templateService->getTemplate()->getAccumulateInOneFile();
            if (null !== $this->templateService->getTemplate()->getExtension()) {
                $extension = '.'.$this->templateService->getTemplate()->getExtension();
            }
        } else {
            $accumulateInOneFile = \in_array($this->format, [TemplateService::MERGED_JSON_FORMAT, TemplateService::MERGED_XML_FORMAT]);
            $useTemplate = false;
            if (\str_contains($this->format, (string) TemplateService::JSON_FORMAT)) {
                $extension = '.json';
            } elseif (\str_contains($this->format, (string) TemplateService::XML_FORMAT)) {
                $extension = '.xml';
            } else {
                $output->writeln(\sprintf('WARNING: Format %s not found', $this->format));

                return -1;
            }
        }

        $accumulatedContent = [];
        $errorList = [];
        $loop = [
            'first' => true,
            'index' => 1,
            'index0' => 0,
            'last' => (1 === $total),
        ];

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if ($this->withBusinessId) {
                    $document = $this->dataService->hitToBusinessDocument($contentType, $result->getHit());
                } else {
                    $document = Document::fromResult($result);
                }

                if ($useTemplate && $this->templateService->hasFilenameTemplate()) {
                    $filename = $this->templateService->renderFilename($document, $contentType, $this->environmentName, [
                        'loop' => $loop,
                    ]).$extension;
                } elseif (null !== $contentType->getBusinessIdField() && isset($result->getData()[$contentType->getBusinessIdField()])) {
                    $filename = $result->getData()[$contentType->getBusinessIdField()].$extension;
                } else {
                    $filename = $result->getId().$extension;
                }

                if ($useTemplate) {
                    try {
                        $content = $this->templateService->render($document, $contentType, $this->environmentName, [
                            'loop' => $loop,
                        ]);
                    } catch (Error $e) {
                        $this->logger->error('log.command.export.template_error', [
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                            EmsFields::LOG_EXCEPTION_FIELD => $e,
                            'format' => $this->format,
                        ]);
                        $errorList[] = 'Error in rendering template for: '.$filename;
                        continue;
                    }
                } else {
                    if ($accumulateInOneFile) {
                        $content = Json::encode($document->getSource());
                    } elseif (\str_contains($this->format, (string) TemplateService::JSON_FORMAT)) {
                        $content = Json::encode($document->getSource(), true);
                    } elseif (\str_contains($this->format, (string) TemplateService::XML_FORMAT)) {
                        $content = $this->templateService->getXml($contentType, $document->getSource(), false, $document->getOuuid());
                    } else {
                        $this->logger->error('log.command.export.unknow_format', [
                            'format' => $this->format,
                        ]);
                        $errorList[] = 'Unknow format: '.$this->format;
                        continue;
                    }
                }

                if ($accumulateInOneFile) {
                    $accumulatedContent[$result->getId()] = $content;
                } else {
                    $zip->addFromString($filename, $content);
                }
                $this->io->progressAdvance();
                ++$loop['index0'];
                ++$loop['index'];
                $loop['first'] = false;
                $loop['last'] = ($total === $loop['index']);
            }
        }

        if ($accumulateInOneFile) {
            if ($useTemplate) {
                $accumulatedContent = \implode('', $accumulatedContent);
            } elseif (\str_contains($this->format, (string) TemplateService::JSON_FORMAT)) {
                $accumulatedContent = Json::encode($accumulatedContent);
            } elseif (\str_contains($this->format, (string) TemplateService::XML_FORMAT)) {
                $accumulatedContent = $this->templateService->getXml($contentType, $accumulatedContent, true);
            } else {
                $output->writeln(\sprintf('WARNING: Format %s not found', $this->format));

                return -1;
            }
            $zip->addFromString('emsExport'.$extension, $accumulatedContent);
        }

        if (\sizeof($errorList) > 0) {
            $zip->addFromString('All-Errors.txt', \implode("\n", $errorList));
        }

        $zip->close();
        $this->io->progressFinish();

        $output->writeln('');
        if (null !== $this->zipFilename) {
            $output->writeln('Export: '.$outZipPath);

            return self::EXECUTE_SUCCESS;
        }

        $hash = $this->storageManager->saveFile($outZipPath, StorageInterface::STORAGE_USAGE_CONFIG);
        $url = ($this->baseUrl ?? '').$this->runtime->assetPath(
            [
                EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
                EmsFields::CONTENT_FILE_NAME_FIELD => 'export.zip',
                EmsFields::CONTENT_MIME_TYPE_FIELD => MimeTypes::APPLICATION_ZIP->value,
            ],
            [],
            'ems_asset',
            EmsFields::CONTENT_FILE_HASH_FIELD,
            EmsFields::CONTENT_FILE_NAME_FIELD,
            EmsFields::CONTENT_MIME_TYPE_FIELD,
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
        $output->writeln('Export is available at: '.$url);

        return self::EXECUTE_SUCCESS;
    }
}
