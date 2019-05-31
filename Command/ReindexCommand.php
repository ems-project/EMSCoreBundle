<?php

namespace EMS\CoreBundle\Command;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexCommand extends EmsCommand
{
    protected $client;
    protected $mapping;
    protected $doctrine;
    protected $logger;
    protected $container;
    /**@var DataService*/
    protected $dataService;
    private $instanceId;
    
    private $count;
    private $deleted;
    private $error;
    
    public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, $container, $instanceId, DataService $dataService)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->container = $container;
        $this->instanceId = $instanceId;
        $this->dataService = $dataService;
        parent::__construct($logger, $client);
        
        $this->count = 0;
        $this->deleted = 0;
        $this->error = 0;
    }
    
    protected function configure()
    {
        $this->logger->info('Configure the ReindexCommand');
        $this
            ->setName('ems:environment:reindex')
            ->setDescription('Reindex an environment in it\'s existing index')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
            )
            ->addArgument(
                'content-type',
                InputArgument::OPTIONAL,
                'If not defined all content types will be re-indexed'
            )
            ->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'Elasticsearch index where to index environment objects'
            )
            ->addOption(
                'sign-data',
                null,
                InputOption::VALUE_NONE,
                'The content won\'t be (re)signed during the reindexing process'
            )
            ->addOption(
                'bulk-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of item that will be indexed together during the same elasticsearch operation',
                1000
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws MappingException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatStyles($output);
        $name = $input->getArgument('name');
        $index = $input->getArgument('index');
        $signData= !$input->getOption('sign-data');



        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');

        if ($input->hasArgument('content-type')) {
            $contentTypes = $ctRepo->findBy(['deleted' => false, 'name' => $input->getArgument('content-type')]);
        } else {
            $contentTypes = $ctRepo->findBy(['deleted' => false]);
        }


        /**@var ContentType $contentType*/
        foreach ($contentTypes as $contentType) {
            $this->reindex($name, $contentType, $index, $output, $signData, $input->getOption('bulk-size'));
        }
    }

    /**
     * @param string $name
     * @param ContentType $contentType
     * @param string $index
     * @param OutputInterface $output
     * @param bool $signData
     * @param int $bulkSize
     * @throws MappingException
     */
    public function reindex($name, ContentType $contentType, $index, OutputInterface $output, $signData = true, $bulkSize = 1000)
    {
        $this->logger->info('Execute the ReindexCommand');
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        
        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository('EMSCoreBundle:Environment');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');
        $environment = $envRepo->findBy(['name' => $name, 'managed' => true]);
        
        if ($environment && count($environment) == 1) {
            /** @var Environment $environment */
            $environment = $environment[0];
            
            if (!$index) {
                $index = $environment->getAlias();
            }
            $page = 0;
            $bulk = [];
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);



            $output->writeln('');
            $output->writeln('Start reindex '.$contentType->getName());
            // create a new progress bar
            $progress = new ProgressBar($output, $paginator->count());
            // start and displays the progress bar
            $progress->start();
            do {
                /** @var Revision $revision */
                foreach ($paginator as $revision) {
                    if ($revision->getDeleted()) {
                        ++$this->deleted;
                        $this->logger->warning('log.reindex.revision.deleted_but_referenced', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        ]);
                    } else {
                        if ($signData) {
                            $this->dataService->sign($revision);
                        }

                        if (empty($bulk) && $revision->getContentType()->getHavePipelines()) {
                            $bulk['pipeline'] = $this->instanceId.$revision->getContentType()->getName();
                        }

                        $bulk['body'][] = [
                                'index' => [
                                    '_index' => $index,
                                    '_type' => $contentType->getName(),
                                    '_id' => $revision->getOuuid(),
                                ]
                            ];

                        $rawData = $revision->getRawData();
                        $rawData[Mapping::PUBLISHED_DATETIME_FIELD] =  (new DateTime())->format(DateTime::ISO8601);
                        $bulk['body'][] = $rawData;
                    }


                    $progress->advance();
                    if (count($bulk['body']) >= (2*$bulkSize)) {
                        $this->treatBulkResponse($this->client->bulk($bulk));
                        unset($bulk);
                        $bulk = [];
                    }
                }

                $em->clear(Revision::class);

                ++$page;
                $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);
            } while ($paginator->getIterator()->count());


            if (count($bulk)) {
                $this->treatBulkResponse($this->client->bulk($bulk));
            }
            
            $progress->finish();
            $output->writeln('');

            $output->writeln(' '.$this->count.' objects are re-indexed in '.$index.' ('.$this->deleted.' not indexed as deleted, '.$this->error.' with indexing error)');
        } else {
            $output->writeln("WARNING: Environment named ".$name." not found");
        }
    }
    
    public function treatBulkResponse($response)
    {
        foreach ($response['items'] as $item) {
            if (isset($item['index']['error'])) {
                ++$this->error;
                $this->logger->warning('log.reindex.revision.error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $item['index']['_type'],
                    EmsFields::LOG_OUUID_FIELD => $item['index']['_id'],
                ]);
            } else {
                ++$this->count;
            }
        }
    }
}
