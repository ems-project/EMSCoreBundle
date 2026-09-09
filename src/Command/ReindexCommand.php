<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Commands;
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Symfony\Component\Translation\t;

#[AsCommand(name: Commands::ENVIRONMENT_REINDEX, description: "Reindex an environment in it's existing index.", aliases: ['ems:environment:reindex'], hidden: false)]
class ReindexCommand extends AbstractCoreCommand
{
    private int $count = 0;
    private int $deleted = 0;
    private int $reloaded = 0;
    private int $error = 0;

    public function __construct(
        protected Registry $doctrine,
        protected LocalizedLoggerInterface $logger,
        protected Mapping $mapping,
        protected ContainerInterface $container,
        protected DataService $dataService,
        private readonly Bulker $bulker,
        private readonly string $defaultBulkSize
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
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
                "The content won't be (re)signed during the reindexing process"
            )
            ->addOption(
                'reload-data',
                null,
                InputOption::VALUE_NONE,
                'Reload the data'
            )
            ->addOption(
                'bulk-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of item that will be indexed together during the same elasticsearch operation',
                $this->defaultBulkSize
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $index = $input->getArgument('index');
        $signData = true === $input->getOption('sign-data');
        $reloadData = true === $input->getOption('reload-data');

        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected environment name');
        }
        if (null !== $index && !\is_string($index)) {
            throw new \RuntimeException('Unexpected index name');
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var ContentTypeRepository $ctRepo */
        $ctRepo = $em->getRepository(ContentType::class);

        $contentTypeName = $input->getArgument('content-type');
        if (\is_string($contentTypeName)) {
            $contentTypes = $ctRepo->findBy(['deleted' => false, 'name' => $contentTypeName]);
        } else {
            $contentTypes = $ctRepo->findBy(['deleted' => false]);
        }

        $bulkSize = (int) $input->getOption('bulk-size');
        if (0 === $bulkSize) {
            throw new \RuntimeException('Unexpected bulk size argument');
        }

        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $this->reindex($name, $contentType, $index, $output, $signData, $bulkSize, $reloadData);
        }

        return Command::SUCCESS;
    }

    public function reindex(string $name, ContentType $contentType, ?string $index, OutputInterface $output, bool $signData = true, int $bulkSize = 1000, bool $reloadData = false): void
    {
        $this->logger->messageNotice(t('message.reindex_command_start', [
            'environment' => $name,
            'content_type' => $contentType->getSingularName(),
        ], 'emsco-core'));

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository(Environment::class);
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository(Revision::class);
        $environment = $envRepo->findBy(['name' => $name, 'managed' => true]);

        if ($environment && 1 === \count($environment)) {
            /** @var Environment $environment */
            $environment = $environment[0];

            if (null === $index) {
                $index = $environment->getAlias();
            }
            $page = 0;
            $this->bulker->setSign($signData);
            $this->bulker->setSize($bulkSize);
            $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page, $bulkSize);

            $output->writeln('');
            $output->writeln('Start reindex '.$contentType->getName());
            $progress = new ProgressBar($output, $paginator->count());
            $progress->start();
            do {
                /** @var Revision $revision */
                foreach ($paginator as $revision) {
                    if ($revision->isLocked()) {
                        $progress->advance();
                        continue;
                    }

                    if ($revision->getDeleted()) {
                        ++$this->deleted;

                        $this->logger->messageWarning(t('message.revision_deleted_but_referenced', [
                            'environment' => $environment->getLabel(),
                            'label' => $revision->getLabel(),
                        ], 'emsco-core'));
                    } else {
                        if ($reloadData) {
                            $this->reloaded += $this->dataService->reloadData($revision, false);
                        }

                        $rawData = $revision->getRawData($environment);
                        $this->bulker->index($contentType->getName(), $revision->giveOuuid(), $index, $rawData);
                    }

                    $progress->advance();
                }

                $em->flush();
                $em->clear();

                ++$page;
                $paginator = $revRepo->getRevisionsPaginatorPerEnvironmentAndContentType($environment, $contentType, $page, $bulkSize);
                $iterator = $paginator->getIterator();
            } while ($iterator instanceof \ArrayIterator && $iterator->count());
            $this->bulker->send(true);

            $progress->finish();
            $output->writeln('');
            $output->writeln(' '.$this->count.' objects are re-indexed in '.$index.' ('.$this->deleted.' not indexed as deleted, '.$this->error.' with indexing error)');

            if ($this->reloaded > 0) {
                $output->writeln(\sprintf('%d documents are reloaded', $this->reloaded));
            }

            $this->logger->messageNotice(t('message.reindex_command_end', [
                'content_type' => $contentType->getSingularName(),
                'environment' => $environment->getLabel(),
                'index' => $index,
            ], 'emsco-core'));
        } else {
            $this->logger->messageNotice(t('message.reindex_command_environment_not_found', [
                'environment' => $name,
            ], 'emsco-core'));

            $output->writeln('WARNING: Environment named '.$name.' not found');
        }
    }
}
