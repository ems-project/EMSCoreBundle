<?php


namespace EMS\CoreBundle\Command;


use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactoryInterface;

class ExportCommand extends EmsCommand
{

    const EXPORT_JSON_FORMATS = 'json';
    const EXPORT_XML_FORMATS = 'xml';
    const EXPORT_MERGED_JSON_FORMATS = 'merged-json';
    const EXPORT_MERGED_XML_FORMATS = 'merged-xml';
    const EXPORT_FORMATS = [ExportCommand::EXPORT_JSON_FORMATS, ExportCommand::EXPORT_JSON_FORMATS, ExportCommand::EXPORT_MERGED_JSON_FORMATS, ExportCommand::EXPORT_MERGED_XML_FORMATS];

    /** @var Client  */
    protected $client;
    /** @var Logger  */
    protected $logger;
    /** @var DataService  */
    protected $dataService;
    /** @var ContentTypeService  */
    protected $contentTypeService;
    /** @var string */
    protected $instanceId;

    public function __construct(Logger $logger, Client $client, DataService $dataService, ContentTypeService $contentTypeService, string $instanceId)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->dataService = $dataService;
        $this->instanceId = $instanceId;
        $this->contentTypeService = $contentTypeService;
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
                sprintf('The format of the output: %s or the id of the content type\' template', \implode(', ', self::EXPORT_FORMATS)),
                'json'
            )
            ->addArgument(
                'query',
                InputArgument::OPTIONAL,
                'The query to run',
                '{}'
            )
            ->addOption(
                'index',
                null,
                InputArgument::OPTIONAL,
                'The index to use for the query, it will use the default environemnt index if not defined'
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $contentTypeName = $input->getArgument('contentTypeName');
        $scrollSize = $input->getOption('scrollSize');
        $scrollTimeout = $input->getOption('scrollTimeout');
        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (! $contentType instanceof ContentType) {
            $output->writeln(sprintf("WARNING: Content type named %s not found", $contentType));
            return null;
        }
        $index = $input->getOption('index');
        if ($index === null) {
            $index = $contentType->getEnvironment()->getAlias();
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

        while (isset($arrayElasticsearchIndex['hits']['hits']) && count($arrayElasticsearchIndex['hits']['hits']) > 0) {
            foreach ($arrayElasticsearchIndex["hits"]["hits"] as $index => $value) {
                $progress->advance();
            }

            $scroll_id = $arrayElasticsearchIndex['_scroll_id'];
            $arrayElasticsearchIndex = $this->client->scroll([
                "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
                "scroll" => $scrollTimeout, // and the same timeout window
            ]);
        }

        $progress->finish();
        $output->writeln("");
        $output->writeln("Migration done");
    }
}
