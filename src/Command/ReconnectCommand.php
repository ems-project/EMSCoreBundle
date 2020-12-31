<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Endpoints\Indices\Mapping\Get;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReconnectCommand extends Command
{
    /** @var ContentTypeService */
    private $contentTypeService;

    /** @var Client */
    protected $client;

    /** @var EnvironmentService */
    protected $environmentService;

    /** @var bool */
    private $singleTypeIndex;

    public function __construct(Client $client, ContentTypeService $contentTypeService, EnvironmentService $environmentService, bool $singleTypeIndex)
    {
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->singleTypeIndex = $singleTypeIndex;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:environment:reconnect')
            ->setDescription('Reconnect single type indexes for an environment alias')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->singleTypeIndex) {
            $output->writeln('This commands is for single type indexes config');
            exit;
        }

        $environmentName = $input->getArgument('name');

        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Unexpected environment name');
        }

        $environment = $this->environmentService->getByName($environmentName);

        if (!$environment) {
            $output->writeln('Environment not found');

            return -1;
        }

        $endpoint = new Get();
        $endpoint->setIndex($environment->getAlias());
        $mappings = $this->client->requestEndpoint($endpoint)->getData();

        foreach ($mappings as $index => $indexMapping) {
            foreach ($indexMapping['mappings'] as $type => $typeMapping) {
                if (isset($typeMapping['_meta']['generator']) && 'elasticms' === $typeMapping['_meta']['generator'] && isset($typeMapping['_meta']['content_type'])) {
                    $contentType = $this->contentTypeService->getByName($typeMapping['_meta']['content_type']);
                    if ($contentType) {
                        $this->contentTypeService->setSingleTypeIndex($environment, $contentType, $index);
                        $output->writeln('Index found for content type: '.$typeMapping['_meta']['content_type']);
                    } else {
                        $output->writeln('Content type not found: '.$typeMapping['_meta']['content_type']);
                    }
                } else {
                    $output->writeln('This mapping was not defined be elasticms: '.$type);
                }
            }
        }

        return 0;
    }
}
