<?php

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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;

class RecomputeCommand extends EmsCommand
{
    /** @var string */
    const LOCK_BY = 'SYSTEM_RECOMPUTE';
    /** @var ObjectManager */
    private $em;
    /** @var DataService */
    private $dataService;
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var PublishService */
    private $publishService;
    /** @var ContentTypeRepository */
    private $contentTypeRepository;
    /** @var RevisionRepository */
    private $revisionRepository;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var IndexService */
    private $indexService;
    /** @var SearchService */
    private $searchService;
    /** @var LoggerInterface */
    protected $logger;

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
            ->addArgument('contentType', InputArgument::REQUIRED, 'content type to recompute')
            ->addOption('force', null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption('missing', null, InputOption::VALUE_NONE, 'will recompute the objects that are missing in their default environment only')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'continue a recompute')
            ->addOption('no-align', null, InputOption::VALUE_NONE, "don't keep the revisions aligned to all already aligned environments")
            ->addOption('cron', null, InputOption::VALUE_NONE, 'optimized for automated recurring recompute calls, tries --continue, when no locks are found for user runs command without --continue')
            ->addOption('ouuid', null, InputOption::VALUE_OPTIONAL, 'recompute a specific revision ouuid', null)
            ->addOption('deep', null, InputOption::VALUE_NONE, 'deep recompute form will be submitted and transformers triggered')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->em instanceof EntityManager) {
            $output->writeln('The entity manager might not be configured correctly');

            return -1;
        }
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->em->getConnection()->setAutoCommit(false);

        $io = new SymfonyStyle($input, $output);

        $contentTypeName = $input->getArgument('contentType');
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $contentType = $this->contentTypeRepository->findOneBy(['name' => $contentTypeName]);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Content type not found');
        }

        if (!$input->getOption('continue') || $input->getOption('cron')) {
            $forceFlag = $input->getOption('force');
            if (!\is_bool($forceFlag)) {
                throw new \RuntimeException('Unexpected force option');
            }
            $cronFlag = $input->getOption('cron');
            if (!\is_bool($cronFlag)) {
                throw new \RuntimeException('Unexpected cron option');
            }
            $ouuid = $input->getOption('ouuid') ? \strval($input->getOption('ouuid')) : null;
            $this->lock($output, $contentType, $forceFlag, $cronFlag, $ouuid);
        }

        $optionDeep = \boolval($input->getOption('deep'));
        $page = 0;
        $limit = 200;
        $paginator = $this->revisionRepository->findAllLockedRevisions($contentType, self::LOCK_BY, $page, $limit);

        $progress = $io->createProgressBar($paginator->count());
        $progress->start();

        $missingInIndex = false;

        if ($input->getOption('missing')) {
            $missingInIndex = $this->contentTypeService->getIndex($contentType);
        }

        do {
            $transactionActive = false;
            /** @var Revision $revision */
            foreach ($paginator as $revision) {
                $revisionType = $this->formFactory->create(RevisionType::class, null, [
                    'migration' => true,
                    'content_type' => $contentType,
                ]);

                $revisionId = $revision->getId();
                if (!\is_int($revisionId)) {
                    throw new \RuntimeException('Unexpected null revision id');
                }

                if ($missingInIndex) {
                    try {
                        $this->searchService->getDocument($contentType, $revision->getOuuid());
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

                if ($optionDeep) {
                    $viewData = $this->dataService->getSubmitData($revisionType->get('data')); //get view data of new revision
                    $revisionType->submit(['data' => $viewData]); // submit new revision (reverse model transformers called
                }

                $objectArray = $newRevision->getRawData();

                //@todo maybe improve the data binding like the migration?

                $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $contentType, $contentType->getName(), $newRevision->getOuuid(), true);
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
            $paginator = $this->revisionRepository->findAllLockedRevisions($contentType, self::LOCK_BY, $page, $limit);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $progress->finish();
        $output->writeln('');

        return 0;
    }

    private function lock(OutputInterface $output, ContentType $contentType, bool $force = false, bool $ifEmpty = false, ?string $ouuid = null): int
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
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
