<?php


namespace EMS\CoreBundle\Command;


use Elasticsearch\Client;
use EMS\CommonBundle\Common\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\TemplateService;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Twig_Error;
use ZipArchive;

class ExportCommand extends EmsCommand
{
    /** @var Client  */
    protected $client;
    /** @var Logger  */
    protected $logger;
    /** @var DataService  */
    protected $dataService;
    /** @var EnvironmentService  */
    protected $environmentService;
    /** @var ContentTypeService  */
    protected $contentTypeService;
    /** @var TemplateService  */
    protected $templateService;
    /** @var RequestRuntime  */
    protected $runtime;
    /** @var string */
    protected $instanceId;

    public function __construct(Logger $logger, Client $client, TemplateService $templateService, DataService $dataService, ContentTypeService $contentTypeService, EnvironmentService $environmentService, RequestRuntime $runtime, string $instanceId)
    {
        $this->logger = $logger;
        $this->templateService = $templateService;
        $this->client = $client;
        $this->dataService = $dataService;
        $this->environmentService = $environmentService;
        $this->instanceId = $instanceId;
        $this->contentTypeService = $contentTypeService;
        $this->runtime = $runtime;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this
            ->setName('ems:contenttype:export')
            ->setDescription('Export a search result of a content type to a specific format')
            ->addArgument(
                'contentTypeName',
                InputArgument::REQUIRED,
                'The document\'s content type name to export'
            )
            ->addArgument(
                'format',
                InputArgument::OPTIONAL,
                sprintf('The format of the output: %s or the id of the content type\' template', \implode(', ', TemplateService::EXPORT_FORMATS)),
                'json'
            )
            ->addArgument(
                'query',
                InputArgument::OPTIONAL,
                'The query to run',
                '{}'
            )
            ->addOption(
                'environment',
                null,
                InputArgument::OPTIONAL,
                'The environment to use for the query, it will use the default environment if not defined'
            )
            ->addOption(
                'withBusinessId',
                null,
                InputOption::VALUE_NONE,
                'Replace internal OUUIDs by business values'
            )
            ->addOption(
                'scrollSize',
                null,
                InputArgument::OPTIONAL,
                'Size of the elasticsearch scroll request',
                100
            )
            ->addOption(
                'scrollTimeout',
                null,
                InputArgument::OPTIONAL,
                'Time to migrate "scrollSize" items i.e. 30s or 2m',
                '1m'
            )
            ->addOption(
                'baseUrl',
                null,
                InputArgument::OPTIONAL,
                'Base url of the application (in order to generate a link)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $contentTypeName = $input->getArgument('contentTypeName');
        $format = $input->getArgument('format');
        $scrollSize = $input->getOption('scrollSize');
        $scrollTimeout = $input->getOption('scrollTimeout');
        $withBusinessId = $input->getOption('withBusinessId');
        $baseUrl = $input->getOption('baseUrl');
        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (! $contentType instanceof ContentType) {
            $output->writeln(sprintf("WARNING: Content type named %s not found", $contentType));
            return null;
        }
        $environmentName = $input->getOption('environment');
        if ($environmentName === null) {
            $index = $contentType->getEnvironment()->getAlias();
        } else {
            $environment = $this->environmentService->getByName($environmentName);
            if ($environment === false) {
                $output->writeln(sprintf("WARNING: Environment named %s not found", $environmentName));
                return null;
            }
            $index = $environment->getAlias();
        }

        $arrayElasticsearchIndex = $this->client->search([
            'index' => $index,
            'type' => $contentTypeName,
            'size' => $scrollSize,
            "scroll" => $scrollTimeout,
            'body' => \json_decode($input->getArgument('query')),
        ]);

        $total = $arrayElasticsearchIndex["hits"]["total"];
        $progress = new ProgressBar($output, $total);
        $progress->start();

        $outZipPath = \tempnam(\sys_get_temp_dir(), 'emsExport') . '.zip';
        $zip = new ZipArchive();
        $zip->open($outZipPath, ZIPARCHIVE::CREATE);
        $extension = '';
        if (!in_array($format, TemplateService::EXPORT_FORMATS)) {
            $this->templateService->init($format);
            $useTemplate = true;
            $accumulateInOneFile = $this->templateService->getTemplate()->getAccumulateInOneFile();
            if ($this->templateService->getTemplate()->getExtension() !== null) {
                $extension = '.' . $this->templateService->getTemplate()->getExtension();
            }
        } else {
            $accumulateInOneFile = in_array($format, [TemplateService::MERGED_JSON_FORMAT, TemplateService::MERGED_XML_FORMAT]);
            $useTemplate = false;
            if (\strpos($format, TemplateService::JSON_FORMAT) !== false) {
                $extension = '.json';
            } elseif (\strpos($format, TemplateService::XML_FORMAT) !== false) {
                $extension = '.xml';
            } else {
                $output->writeln(sprintf("WARNING: Format %s not found", $format));
                return null;
            }
        }


        $accumulatedContent = [];
        $errorList = [];

        while (isset($arrayElasticsearchIndex['hits']['hits']) && count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
                if ($contentType->getBusinessIdField() !== null && isset($value['_source'][$contentType->getBusinessIdField()])) {
                    $filename = $value['_source'][$contentType->getBusinessIdField()] . $extension;
                } else {
                    $filename = $value['_id'] . $extension;
                }

                if ($withBusinessId) {
                    $document = $this->dataService->hitToBusinessDocument($contentType, $value);
                } else {
                    $document = new Document($contentType->getName(), $value['_id'], $value['_source']);
                }

                if ($useTemplate) {
                    try {
                        $content = $this->templateService->render($document, $contentType, 'ssss');
                    } catch (Twig_Error $e) {
                        $this->logger->error('log.command.export.template_error', [
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                            EmsFields::LOG_EXCEPTION_FIELD => $e,
                            'template_id' => $format,
                        ]);
                        $errorList[] = "Error in rendering template for: " . $filename;
                        continue;
                    }
                } else {
                    if ($accumulateInOneFile) {
                        $content = $document->getSource();
                    } elseif (\strpos($format, TemplateService::JSON_FORMAT) !== false) {
                        $content = \json_encode($document->getSource());
                    } elseif (\strpos($format, TemplateService::XML_FORMAT) !== false) {
                        $content = $this->templateService->getXml($contentType, $document->getSource(), false, $document->getOuuid());
                    } else {
                        $this->logger->error('log.command.export.unknow_format', [
                            'format' => $format,
                        ]);
                        $errorList[] = "Unknow format: " . $format;
                        continue;
                    }
                }

                if ($accumulateInOneFile) {
                    $accumulatedContent[$value['_id']] = $content;
                } else {
                    $zip->addFromString($filename, $content);
                }
                $progress->advance();
            }

            $scroll_id = $arrayElasticsearchIndex['_scroll_id'];
            $arrayElasticsearchIndex = $this->client->scroll([
                "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
                "scroll" => $scrollTimeout, // and the same timeout window
            ]);
        }

        if ($accumulateInOneFile) {
            if ($useTemplate) {
                $accumulatedContent = implode('', $accumulatedContent);
            } elseif (\strpos($format, TemplateService::JSON_FORMAT) !== false) {
                $accumulatedContent = \json_encode($accumulatedContent);
            } elseif (\strpos($format, TemplateService::XML_FORMAT) !== false) {
                $accumulatedContent = $this->templateService->getXml($contentType, $accumulatedContent, true);
            } else {
                $output->writeln(sprintf("WARNING: Format %s not found", $format));
                return null;
            }
            $zip->addFromString('emsExport' . $extension, $accumulatedContent);
        }

        if (sizeof($errorList) > 0) {
            $zip->addFromString("All-Errors.txt", implode("\n", $errorList));
        }

        $zip->close();
        $progress->finish();
        $output->writeln("");
        $output->writeln("Export done " . $outZipPath);

        if ($baseUrl !== null) {
            $output->writeln("URL: " . $baseUrl . '/' . $this->runtime->assetPath([
                EmsFields::CONTENT_FILE_NAME_FIELD_ => 'export.zip',
            ], [
                EmsFields::ASSET_CONFIG_FILE_NAMES => [$outZipPath],
            ]));
        }
    }
}
