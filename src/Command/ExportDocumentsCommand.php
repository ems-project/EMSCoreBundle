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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\Error;
use ZipArchive;

class ExportDocumentsCommand extends EmsCommand
{
    /** @var Client */
    protected $client;
    /** @var Logger */
    protected $logger;
    /** @var DataService */
    protected $dataService;
    /** @var EnvironmentService */
    protected $environmentService;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var TemplateService */
    protected $templateService;
    /** @var RequestRuntime */
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

    protected function configure(): void
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
                \sprintf('The format of the output: %s or the id of the content type\' template', \implode(', ', TemplateService::EXPORT_FORMATS)),
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contentTypeName = $input->getArgument('contentTypeName');
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name argument');
        }
        $format = $input->getArgument('format');
        if (!\is_string($format)) {
            throw new \RuntimeException('Unexpected format argument');
        }
        $scrollSize = $input->getOption('scrollSize');
        $scrollTimeout = $input->getOption('scrollTimeout');
        $withBusinessId = $input->getOption('withBusinessId');
        $baseUrl = $input->getOption('baseUrl');
        if (null !== $baseUrl && !\is_string($baseUrl)) {
            throw new \RuntimeException('Unexpected base url option');
        }
        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            $output->writeln(\sprintf('WARNING: Content type named %s not found', $contentType));

            return -1;
        }
        $environmentName = $input->getOption('environment');

        if (null === $environmentName) {
            $environment = $contentType->getEnvironment();
            if (null === $environment) {
                throw new \RuntimeException('Environment not found');
            }
            $index = $environment->getAlias();
            $environmentName = $environment->getName();
        } else {
            if (!\is_string($environmentName)) {
                throw new \RuntimeException('Environment name as to be a string');
            }
            $environment = $this->environmentService->getByName($environmentName);
            if (false === $environment) {
                $output->writeln(\sprintf('WARNING: Environment named %s not found', $environmentName));

                return -1;
            }
            $index = $environment->getAlias();
        }
        $query = $input->getArgument('query');
        if (!\is_string($query)) {
            throw new \RuntimeException('Unexpected query argument');
        }

        $arrayElasticsearchIndex = $this->client->search([
            'index' => $index,
            'type' => $contentTypeName,
            'size' => $scrollSize,
            'scroll' => $scrollTimeout,
            'body' => \json_decode($query),
        ]);

        $total = $arrayElasticsearchIndex['hits']['total'];
        $progress = new ProgressBar($output, $total);
        $progress->start();

        $outZipPath = \tempnam(\sys_get_temp_dir(), 'emsExport').'.zip';
        $zip = new ZipArchive();
        $zip->open($outZipPath, ZIPARCHIVE::CREATE);
        $extension = '';
        if (!\in_array($format, TemplateService::EXPORT_FORMATS)) {
            $this->templateService->init($format);
            $useTemplate = true;
            $accumulateInOneFile = $this->templateService->getTemplate()->getAccumulateInOneFile();
            if (null !== $this->templateService->getTemplate()->getExtension()) {
                $extension = '.'.$this->templateService->getTemplate()->getExtension();
            }
        } else {
            $accumulateInOneFile = \in_array($format, [TemplateService::MERGED_JSON_FORMAT, TemplateService::MERGED_XML_FORMAT]);
            $useTemplate = false;
            if (false !== \strpos($format, TemplateService::JSON_FORMAT)) {
                $extension = '.json';
            } elseif (false !== \strpos($format, TemplateService::XML_FORMAT)) {
                $extension = '.xml';
            } else {
                $output->writeln(\sprintf('WARNING: Format %s not found', $format));

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

        while (isset($arrayElasticsearchIndex['hits']['hits']) && \count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            foreach ($arrayElasticsearchIndex['hits']['hits'] as $value) {
                if (null !== $contentType->getBusinessIdField() && isset($value['_source'][$contentType->getBusinessIdField()])) {
                    $filename = $value['_source'][$contentType->getBusinessIdField()].$extension;
                } else {
                    $filename = $value['_id'].$extension;
                }

                if ($withBusinessId) {
                    $document = $this->dataService->hitToBusinessDocument($contentType, $value);
                } else {
                    $document = new Document($contentType->getName(), $value['_id'], $value['_source']);
                }

                if ($useTemplate) {
                    try {
                        $content = $this->templateService->render($document, $contentType, $environmentName, [
                            'loop' => $loop,
                        ]);
                    } catch (Error $e) {
                        $this->logger->error('log.command.export.template_error', [
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                            EmsFields::LOG_EXCEPTION_FIELD => $e,
                            'template_id' => $format,
                        ]);
                        $errorList[] = 'Error in rendering template for: '.$filename;
                        continue;
                    }
                } else {
                    if ($accumulateInOneFile) {
                        $content = $document->getSource();
                    } elseif (false !== \strpos($format, TemplateService::JSON_FORMAT)) {
                        $content = \json_encode($document->getSource());
                    } elseif (false !== \strpos($format, TemplateService::XML_FORMAT)) {
                        $content = $this->templateService->getXml($contentType, $document->getSource(), false, $document->getOuuid());
                    } else {
                        $this->logger->error('log.command.export.unknow_format', [
                            'format' => $format,
                        ]);
                        $errorList[] = 'Unknow format: '.$format;
                        continue;
                    }
                }

                if ($accumulateInOneFile) {
                    $accumulatedContent[$value['_id']] = $content;
                } else {
                    $zip->addFromString($filename, $content);
                }
                $progress->advance();
                ++$loop['index0'];
                ++$loop['index'];
                $loop['first'] = false;
                $loop['last'] = ($total === $loop['index']);
            }

            $scroll_id = $arrayElasticsearchIndex['_scroll_id'];
            $arrayElasticsearchIndex = $this->client->scroll([
                'scroll_id' => $scroll_id,
                'scroll' => $scrollTimeout,
            ]);
        }

        if ($accumulateInOneFile) {
            if ($useTemplate) {
                $accumulatedContent = \implode('', $accumulatedContent);
            } elseif (false !== \strpos($format, TemplateService::JSON_FORMAT)) {
                $accumulatedContent = \json_encode($accumulatedContent);
            } elseif (false !== \strpos($format, TemplateService::XML_FORMAT)) {
                $accumulatedContent = $this->templateService->getXml($contentType, $accumulatedContent, true);
            } else {
                $output->writeln(\sprintf('WARNING: Format %s not found', $format));

                return -1;
            }
            $zip->addFromString('emsExport'.$extension, $accumulatedContent);
        }

        if (\sizeof($errorList) > 0) {
            $zip->addFromString('All-Errors.txt', \implode("\n", $errorList));
        }

        $zip->close();
        $progress->finish();
        $output->writeln('');

        if (null === $baseUrl) {
            $output->writeln('Export: '.$outZipPath);
        } else {
            $output->writeln('Export: '.$baseUrl.$this->runtime->assetPath(
                [
                EmsFields::CONTENT_FILE_NAME_FIELD_ => 'export.zip',
                ],
                [
                EmsFields::ASSET_CONFIG_FILE_NAMES => [$outZipPath],
                ],
                'ems_asset',
                EmsFields::CONTENT_FILE_HASH_FIELD,
                EmsFields::CONTENT_FILE_NAME_FIELD,
                EmsFields::CONTENT_MIME_TYPE_FIELD,
                UrlGeneratorInterface::ABSOLUTE_PATH
            ));
        }

        return 0;
    }
}
