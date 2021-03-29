<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexCommand extends EmsCommand
{
    /** @var Mapping */
    protected $mapping;
    /** @var Registry */
    protected $doctrine;
    /** @var LoggerInterface */
    protected $logger;
    /** @var ContainerInterface */
    protected $container;
    /** @var DataService */
    protected $dataService;
    /** @var string */
    private $instanceId;
    /** @var int */
    private $count;
    /** @var int */
    private $deleted;
    /** @var int */
    private $error;
    /** @var Bulker */
    private $bulker;
    /** @var string */
    private $defaultBulkSize;

    public function __construct(Registry $doctrine, LoggerInterface $logger, Mapping $mapping, ContainerInterface $container, string $instanceId, DataService $dataService, Bulker $bulker, string $defaultBulkSize)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->mapping = $mapping;
        $this->container = $container;
        $this->instanceId = $instanceId;
        $this->dataService = $dataService;
        $this->bulker = $bulker;
        $this->defaultBulkSize = $defaultBulkSize;
        parent::__construct();

        $this->count = 0;
        $this->deleted = 0;
        $this->error = 0;
    }

    protected function configure(): void
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
                $this->defaultBulkSize
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->formatStyles($output);
        $name = $input->getArgument('name');
        $index = $input->getArgument('index');
        $signData = true === $input->getOption('sign-data');

        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected environment name');
        }
        if (null !== $index && !\is_string($index)) {
            throw new \RuntimeException('Unexpected index name');
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypeName = $input->getArgument('content-type');
        if (\is_string($contentTypeName)) {
            $contentTypes = $ctRepo->findBy(['deleted' => false, 'name' => $contentTypeName]);
        } else {
            $contentTypes = $ctRepo->findBy(['deleted' => false]);
        }

        $bulkSize = \intval($input->getOption('bulk-size'));
        if (0 === $bulkSize) {
            throw new \RuntimeException('Unexpected bulk size argument');
        }

        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $this->reindex($name, $contentType, $index, $output, $signData, $bulkSize);
        }

        return 0;
    }

    public function reindex(string $name, ContentType $contentType, ?string $index, OutputInterface $output, bool $signData = true, int $bulkSize = 1000): void
    {
        $this->logger->notice('command.reindex.start', [
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
            EmsFields::LOG_ENVIRONMENT_FIELD => $name,
        ]);

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository('EMSCoreBundle:Environment');
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');
        $environment = $envRepo->findBy(['name' => $name, 'managed' => true]);

        if ($environment && 1 == \count($environment)) {
            /** @var Environment $environment */
            $environment = $environment[0];

            if (null === $index) {
                $index = $environment->getAlias();
            }
            $page = 0;
            $this->bulker->setSign($signData);
            $this->bulker->setSize($bulkSize);
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);

            $output->writeln('');
            $output->writeln('Start reindex '.$contentType->getName());
            $progress = new ProgressBar($output, $paginator->count());
            $progress->start();
            do {
                /** @var Revision $revision */
                foreach ($paginator as $revision) {
                    if ($revision->getDeleted()) {
                        ++$this->deleted;
                        $this->logger->warning('log.reindex.revision.deleted_but_referenced', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        ]);
                    } else {
                        $rawData = $revision->getRawData();
                        $this->bulker->index($contentType->getName(), $revision->getOuuid(), $index, $rawData);
                    }

                    $progress->advance();
                }

                $em->clear(Revision::class);

                ++$page;
                $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page);
                $iterator = $paginator->getIterator();
            } while ($iterator instanceof \ArrayIterator && $iterator->count());
            $this->bulker->send(true);

            $progress->finish();
            $output->writeln('');

            $output->writeln(' '.$this->count.' objects are re-indexed in '.$index.' ('.$this->deleted.' not indexed as deleted, '.$this->error.' with indexing error)');

            $this->logger->notice('command.reindex.end', [
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $name,
                'index' => $index,
                'deleted' => $this->deleted,
                'with_error' => $this->error,
                'total' => $this->count,
            ]);
        } else {
            $this->logger->warning('command.reindex.environment_not_found', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $name,
            ]);

            $output->writeln('WARNING: Environment named '.$name.' not found');
        }
    }

    /**
     * @param array<mixed> $response
     */
    public function treatBulkResponse(array $response): void
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
