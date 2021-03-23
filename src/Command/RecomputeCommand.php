<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;

final class RecomputeCommand extends Command
{
    private ContentType $contentType;
    private bool $optionDeep;
    private bool $forceFlag;
    private bool $cronFlag;
    private ?string $ouuid;
    private ObjectManager $em;
    private DataService $dataService;
    private FormFactoryInterface $formFactory;
    private PublishService $publishService;
    private ContentTypeRepository $contentTypeRepository;
    private RevisionRepository $revisionRepository;
    private ContentTypeService $contentTypeService;
    private IndexService $indexService;
    private SearchService $searchService;
    private SymfonyStyle $io;
    private string $query;
    protected LoggerInterface $logger;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const OPTION_FORCE = 'force';
    private const OPTION_MISSING = 'missing';
    private const OPTION_CONTINUE = 'continue';
    private const OPTION_NOALIGN = 'no-align';
    private const OPTION_CRON = 'cron';
    private const OPTION_OUUID = 'ouuid';
    private const OPTION_DEEP = 'deep';
    private const OPTION_QUERY = 'query';
    private const LOCK_BY = 'SYSTEM_RECOMPUTE';

    public function __construct(
        DataService $dataService,
        Registry $doctrine,
        FormFactoryInterface $formFactory,
        PublishService $publishService,
        LoggerInterface $logger,
        ContentTypeService $contentTypeService,
        ContentTypeRepository $contentTypeRepository,
        RevisionRepository $revisionRepository,
        IndexService $indexService,
        SearchService $searchService
    ) {
        $this->logger = $logger;
        parent::__construct();

        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->publishService = $publishService;
        $this->contentTypeService = $contentTypeService;

        $this->em = $doctrine->getManager();
        $this->contentTypeRepository = $contentTypeRepository;
        $this->revisionRepository = $revisionRepository;
        $this->indexService = $indexService;
        $this->searchService = $searchService;
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:contenttype:recompute')
            ->setDescription('Recompute a content type')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'content type to recompute')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption(self::OPTION_MISSING, null, InputOption::VALUE_NONE, 'will recompute the objects that are missing in their default environment only')
            ->addOption(self::OPTION_CONTINUE, null, InputOption::VALUE_NONE, 'continue a recompute')
            ->addOption(self::OPTION_NOALIGN, null, InputOption::VALUE_NONE, "don't keep the revisions aligned to all already aligned environments")
            ->addOption(self::OPTION_CRON, null, InputOption::VALUE_NONE, 'optimized for automated recurring recompute calls, tries --continue, when no locks are found for user runs command without --continue')
            ->addOption(self::OPTION_OUUID, null, InputOption::VALUE_OPTIONAL, 'recompute a specific revision ouuid', null)
            ->addOption(self::OPTION_DEEP, null, InputOption::VALUE_NONE, 'deep recompute form will be submitted and transformers triggered')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'ES query', '{}')
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('content-type recompute command');

        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $contentType = $this->contentTypeRepository->findByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Content type not found');
        }
        $this->contentType = $contentType;

        if (!$input->getOption(self::OPTION_CONTINUE) || $input->getOption(self::OPTION_CRON)) {
            $forceFlag = $input->getOption(self::OPTION_FORCE);
            if (!\is_bool($forceFlag)) {
                throw new \RuntimeException('Unexpected force option');
            }
            $this->forceFlag = $forceFlag;
            $cronFlag = $input->getOption(self::OPTION_CRON);
            if (!\is_bool($cronFlag)) {
                throw new \RuntimeException('Unexpected cron option');
            }
            $this->cronFlag = $cronFlag;
            $this->ouuid = $input->getOption(self::OPTION_OUUID) ? \strval($input->getOption(self::OPTION_OUUID)) : null;
        }

        if (null !== $input->getOption(self::OPTION_QUERY)) {
            $this->query = \strval($input->getOption('query'));
            \json_decode($this->query, true);
            if (\json_last_error() > 0) {
                throw new \RuntimeException(\sprintf('Invalid json query %s', $this->query));
            }
        }

        $this->optionDeep = \boolval($input->getOption(self::OPTION_DEEP));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->em instanceof EntityManager) {
            $output->writeln('The entity manager might not be configured correctly');

            return -1;
        }
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->em->getConnection()->setAutoCommit(false);

        if (!$input->getOption(self::OPTION_CONTINUE) || $input->getOption(self::OPTION_CRON)) {
            $this->lock($output, $this->contentType, $this->forceFlag, $this->cronFlag, $this->ouuid, $this->query);
        }

        $page = 0;
        $limit = 200;
        $paginator = $this->revisionRepository->findAllLockedRevisions($this->contentType, self::LOCK_BY, $page, $limit);

        $progress = $this->io->createProgressBar($paginator->count());
        $progress->start();

        $missingInIndex = false;
        if ($input->getOption(self::OPTION_MISSING)) {
            $missingInIndex = $this->contentTypeService->getIndex($this->contentType);
        }

        do {
            $transactionActive = false;
            /** @var Revision $revision */
            foreach ($paginator as $revision) {
                $revisionType = $this->formFactory->create(RevisionType::class, null, [
                    'migration' => true,
                    'content_type' => $this->contentType,
                ]);

                $revisionId = $revision->getId();
                if (!\is_int($revisionId)) {
                    throw new \RuntimeException('Unexpected null revision id');
                }

                if ($missingInIndex) {
                    try {
                        $this->searchService->getDocument($this->contentType, $revision->getOuuid());
                        $this->revisionRepository->unlockRevision($revisionId);
                        $progress->advance();
                        continue;
                    } catch (NotFoundException $e) {
                    }
                }
                $transactionActive = true;

                /** @var Revision $revision */
                $newRevision = $revision->convertToDraft();
                $revisionType->setData($newRevision); //bind new revision on form

                if ($this->optionDeep) {
                    $viewData = $this->dataService->getSubmitData($revisionType->get('data')); //get view data of new revision
                    $revisionType->submit(['data' => $viewData]); // submit new revision (reverse model transformers called
                }

                $objectArray = $newRevision->getRawData();

                //@todo maybe improve the data binding like the migration?

                $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $this->contentType, $this->contentType->getName(), $newRevision->getOuuid(), true);
                $newRevision->setRawData($objectArray);

                $revision->close(new \DateTime('now'));
                $newRevision->setDraft(false);

                $this->dataService->sign($revision);
                $this->dataService->sign($newRevision);

                $this->em->persist($revision);
                $this->em->persist($newRevision);
                $this->em->flush();

                $this->indexService->indexRevision($newRevision);

                if (!$input->getOption('no-align')) {
                    foreach ($revision->getEnvironments() as $environment) {
                        $this->logger->info('published to {env}', ['env' => $environment->getName()]);
                        $this->publishService->publish($newRevision, $environment, true);
                    }
                }

                $this->revisionRepository->unlockRevision($revisionId);
                $newRevisionId = $newRevision->getId();
                if (!\is_int($newRevisionId)) {
                    throw new \RuntimeException('Unexpected null revision id');
                }
                $this->revisionRepository->unlockRevision($newRevisionId);

                $progress->advance();
            }

            if ($transactionActive) {
                $this->em->commit();
            }
            $this->em->clear(Revision::class);
            $paginator = $this->revisionRepository->findAllLockedRevisions($this->contentType, self::LOCK_BY, $page, $limit);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $progress->finish();
        $output->writeln('');

        return 0;
    }

    private function lock(OutputInterface $output, ContentType $contentType, bool $force = false, bool $ifEmpty = false, ?string $ouuid = null, string $query): int
    {
        $application = $this->getApplication();
        if (null === $application) {
            throw new \RuntimeException('Application instance not found');
        }
        $command = $application->find('ems:contenttype:lock');
        $arguments = [
            'command' => 'ems:contenttype:lock',
            'contentType' => $contentType->getName(),
            'time' => '+1day',
            '--user' => self::LOCK_BY,
            '--force' => $force,
            '--if-empty' => $ifEmpty,
            '--ouuid' => $ouuid,
            '--query' => $query,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
