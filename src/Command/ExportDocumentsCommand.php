<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Document;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\TemplateService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\Error;

class ExportDocumentsCommand extends EmsCommand
{
    public const OUTPUT_FILE_ARGUMENT = 'outputFile';
    /** @var LoggerInterface */
    protected $logger;
    /** @var DataService */
    protected $dataService;
    /** @var EnvironmentService */
    protected $environmentService;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var TemplateService */
    protected $templateService;
    /** @var AssetRuntime */
    protected $runtime;
    /** @var string */
    protected $instanceId;
    /** @var ElasticaService */
    private $elasticaService;

    public function __construct(LoggerInterface $logger, TemplateService $templateService, DataService $dataService, ContentTypeService $contentTypeService, EnvironmentService $environmentService, AssetRuntime $runtime, ElasticaService $elasticaService, string $instanceId)
    {
        $this->logger = $logger;
        $this->templateService = $templateService;
        $this->dataService = $dataService;
        $this->environmentService = $environmentService;
        $this->instanceId = $instanceId;
        $this->contentTypeService = $contentTypeService;
        $this->runtime = $runtime;
        $this->elasticaService = $elasticaService;
        parent::__construct();
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
                \sprintf('The format of the output: %s or the name of the content type\'s action', \implode(', ', TemplateService::EXPORT_FORMATS)),
                'json'
            )
            ->addArgument(
                'query',
                InputArgument::OPTIONAL,
                'The query to run',
                '{}'
            )
            ->addArgument(
                self::OUTPUT_FILE_ARGUMENT,
                InputArgument::OPTIONAL,
                'The zip output file',
                null
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
                '100'
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
        $scrollSize = \intval($input->getOption('scrollSize'));
        $scrollTimeout = $input->getOption('scrollTimeout');
        if (!\is_string($scrollTimeout)) {
            throw new \RuntimeException('Unexpected scroll timeout argument');
        }
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
        $body = \json_decode($query, true);
        if (\json_last_error() > 0) {
            throw new \RuntimeException(\sprintf('Invalid json passed! %s', \json_last_error_msg()));
        }

        if (isset($body['sort'])) {
            unset($body['sort']);
        }

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $index,
            'type' => $contentTypeName,
            'size' => $scrollSize,
            'body' => $body,
        ]);

        $scroll = $this->elasticaService->scroll($search, $scrollTimeout);
        $total = $this->elasticaService->count($search);

        $progress = new ProgressBar($output, $total);
        $progress->start();

        $outZipPath = $input->getArgument(self::OUTPUT_FILE_ARGUMENT);
        if (!\is_string($outZipPath)) {
            $outZipPath = \tempnam(\sys_get_temp_dir(), 'emsExport').'.zip';
        }
        $zip = new \ZipArchive();
        $zip->open($outZipPath, \ZipArchive::CREATE);
        $extension = '';
        if (!\in_array($format, TemplateService::EXPORT_FORMATS)) {
            $this->templateService->init($format, $contentType);
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

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if ($withBusinessId) {
                    $document = $this->dataService->hitToBusinessDocument($contentType, $result->getHit());
                } else {
                    $document = new Document($contentType->getName(), $result->getId(), $result->getData());
                }

                if ($useTemplate && $this->templateService->hasFilenameTemplate()) {
                    $filename = $this->templateService->renderFilename($document, $contentType, $environmentName, [
                        'loop' => $loop,
                    ]).$extension;
                } elseif (null !== $contentType->getBusinessIdField() && isset($result->getData()[$contentType->getBusinessIdField()])) {
                    $filename = $result->getData()[$contentType->getBusinessIdField()].$extension;
                } else {
                    $filename = $result->getId().$extension;
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
                            'format' => $format,
                        ]);
                        $errorList[] = 'Error in rendering template for: '.$filename;
                        continue;
                    }
                } else {
                    if ($accumulateInOneFile) {
                        $content = Json::encode($document->getSource());
                    } elseif (false !== \strpos($format, TemplateService::JSON_FORMAT)) {
                        $content = Json::encode($document->getSource(), true);
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
                    $accumulatedContent[$result->getId()] = $content;
                } else {
                    $zip->addFromString($filename, $content);
                }
                $progress->advance();
                ++$loop['index0'];
                ++$loop['index'];
                $loop['first'] = false;
                $loop['last'] = ($total === $loop['index']);
            }
        }

        if ($accumulateInOneFile) {
            if ($useTemplate) {
                $accumulatedContent = \implode('', $accumulatedContent);
            } elseif (false !== \strpos($format, TemplateService::JSON_FORMAT)) {
                $accumulatedContent = Json::encode($accumulatedContent);
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

        if (null !== $baseUrl) {
            $outZipPath = $baseUrl.$this->runtime->assetPath(
                [
                    EmsFields::CONTENT_FILE_NAME_FIELD_ => 'export.zip',
                    EmsFields::CONTENT_FILE_HASH_FIELD_ => \sha1_file($outZipPath),
                ],
                [
                EmsFields::ASSET_CONFIG_FILE_NAMES => [$outZipPath],
                ],
                'ems_asset',
                EmsFields::CONTENT_FILE_HASH_FIELD,
                EmsFields::CONTENT_FILE_NAME_FIELD,
                EmsFields::CONTENT_MIME_TYPE_FIELD,
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
        }

        $output->writeln('');
        $output->writeln('Export: '.$outZipPath);

        return 0;
    }
}
