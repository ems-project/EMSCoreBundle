<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
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
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var DataService
     */
    private $dataService;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var PublishService
     */
    private $publishService;

    /**
     * @var ContentTypeRepository
     */
    private $contentTypeRepository;

    /**
     * @var RevisionRepository
     */
    private $revisionRepository;

    /**
     * @var ContentTypeService
     */
    private $contentTypeService;

    const LOCK_BY = 'SYSTEM_RECOMPUTE';

    /**
     * @inheritdoc
     */
    public function __construct(
        DataService $dataService,
        Registry $doctrine,
        FormFactoryInterface $formFactory,
        PublishService $publishService,
        LoggerInterface $logger,
        Client $client,
        ContentTypeService $contentTypeService,
        ContentTypeRepository $contentTypeRepository,
        RevisionRepository $revisionRepository
    ) {
        parent::__construct($logger, $client);

        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->publishService = $publishService;
        $this->contentTypeService = $contentTypeService;

        $this->em = $doctrine->getManager();
        $this->contentTypeRepository = $contentTypeRepository;
        $this->revisionRepository = $revisionRepository;
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
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'recompute a specific id')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->em instanceof EntityManager) {
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
            $forceFlag = (bool) $input->getOption('force');
            if (!\is_bool($forceFlag)) {
                throw new \RuntimeException('Unexpected force option');
            }
            $cronFlag = (bool) $input->getOption('cron');
            if (!\is_bool($cronFlag)) {
                throw new \RuntimeException('Unexpected cron option');
            }
            $idFlag = (bool) $input->getOption('id');
            if (!\is_bool($idFlag)) {
                throw new \RuntimeException('Unexpected id option');
            }
            $this->lock($output, $contentType, $forceFlag, $cronFlag, $idFlag);
        }

        $page = 0;
        $limit = 200;
        $paginator = $this->revisionRepository->findAllLockedRevisions($contentType, self::LOCK_BY, $page, $limit);

        $progress = $io->createProgressBar($paginator->count());
        $progress->start();

        $revisionType = $this->formFactory->create(RevisionType::class, null, [
            'migration' => true,
            'content_type' => $contentType,
        ]);

        $missingInIndex = false;

        if ($input->getOption('missing')) {
            $missingInIndex = $this->contentTypeService->getIndex($contentType);
        }

        
        do {
            $transactionActive = false;
            /**@var Revision $revision*/
            foreach ($paginator as $revision) {
                $revisionId = $revision->getId();
                if (!\is_int($revisionId)) {
                    throw new \RuntimeException('Unexpected null revision id');
                }

                if ($missingInIndex) {
                    try {
                        $this->client->get([
                            'index' => $missingInIndex,
                            'type' => $contentType->getName(),
                            'id' => $revision->getOuuid(),
                        ]);
                        $this->revisionRepository->unlockRevision($revisionId);
                        $progress->advance();
                        continue;
                    } catch (Missing404Exception $e) {
                    }
                }

                $transactionActive = true;

                /** @var Revision $revision */
                $newRevision = $revision->convertToDraft();

                $revisionType->setData($newRevision);
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

                $this->client->index([
                    'index' => $this->contentTypeService->getIndex($contentType),
                    'body' => $newRevision->getRawData(),
                    'id' => $newRevision->getOuuid(),
                    'type' => $contentType->getName(),
                ]);

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
        return 0;
    }

    private function lock(OutputInterface $output, ContentType $contentType, bool $force = false, bool $ifEmpty = false, bool $id = false): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('Application instance not found');
        }
        $command = $application->find('ems:contenttype:lock');
        $arguments = [
            'command'     => 'ems:contenttype:lock',
            'contentType' => $contentType->getName(),
            'time'        => '+1day',
            '--user'      => self::LOCK_BY,
            '--force'     => $force,
            '--if-empty'  => $ifEmpty,
            '--id'        => $id
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
