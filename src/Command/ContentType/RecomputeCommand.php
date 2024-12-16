<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ContentType;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Notification;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactoryInterface;

#[AsCommand(
    name: Commands::CONTENT_TYPE_RECOMPUTE,
    description: 'Recompute a content type.',
    hidden: false,
    aliases: ['ems:contenttype:recompute']
)]
final class RecomputeCommand extends AbstractCommand
{
    private EntityManager $em;
    private Connection $conn;
    private ContentType $contentType;
    private bool $isChanged;
    private bool $isDeep;
    private bool $isForce;
    private bool $isCron;
    private bool $isContinue;
    private bool $isMissing;
    private bool $isAlign;
    private ?string $ouuid = null;
    private string $query;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const OPTION_CHANGED = 'changed';
    private const OPTION_FORCE = 'force';
    private const OPTION_MISSING = 'missing';
    private const OPTION_CONTINUE = 'continue';
    private const OPTION_NO_ALIGN = 'no-align';
    private const OPTION_CRON = 'cron';
    private const OPTION_OUUID = 'ouuid';
    private const OPTION_DEEP = 'deep';
    private const OPTION_QUERY = 'query';

    private const LOCK_BY = 'SYSTEM_RECOMPUTE';

    public function __construct(
        private readonly DataService $dataService,
        private readonly Registry $doctrine,
        private readonly FormFactoryInterface $formFactory,
        private readonly PublishService $publishService,
        protected LoggerInterface $logger,
        private readonly ContentTypeService $contentTypeService,
        private readonly RevisionRepository $revisionRepository,
        private readonly IndexService $indexService,
        private readonly SearchService $searchService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this

            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'content type to recompute')
            ->addOption(self::OPTION_CHANGED, null, InputOption::VALUE_NONE, 'only create new revision if the hash changed after recompute')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption(self::OPTION_MISSING, null, InputOption::VALUE_NONE, 'will recompute the objects that are missing in their default environment only')
            ->addOption(self::OPTION_CONTINUE, null, InputOption::VALUE_NONE, 'continue a recompute')
            ->addOption(self::OPTION_NO_ALIGN, null, InputOption::VALUE_NONE, "don't keep the revisions aligned to all already aligned environments")
            ->addOption(self::OPTION_CRON, null, InputOption::VALUE_NONE, 'optimized for automated recurring recompute calls, tries --continue, when no locks are found for user runs command without --continue')
            ->addOption(self::OPTION_OUUID, null, InputOption::VALUE_OPTIONAL, 'recompute a specific revision ouuid')
            ->addOption(self::OPTION_DEEP, null, InputOption::VALUE_NONE, 'deep recompute form will be submitted and transformers triggered')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'ES query', '{}')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('content-type recompute command');

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $this->em = $em;
        $this->conn = $em->getConnection();

        $contentTypeName = $this->getArgumentString(self::ARGUMENT_CONTENT_TYPE);
        $this->contentType = $this->contentTypeService->giveByName($contentTypeName);

        $this->isChanged = $this->getOptionBool(self::OPTION_CHANGED);
        $this->isDeep = $this->getOptionBool(self::OPTION_DEEP);
        $this->isForce = $this->getOptionBool(self::OPTION_FORCE);
        $this->isCron = $this->getOptionBool(self::OPTION_CRON);
        $this->isContinue = $this->getOptionBool(self::OPTION_CONTINUE);
        $this->isMissing = $this->getOptionBool(self::OPTION_MISSING);
        $this->isAlign = false === $this->getOptionBool(self::OPTION_NO_ALIGN);
        $this->ouuid = $this->getOptionStringNull(self::OPTION_OUUID);

        if (null !== $input->getOption(self::OPTION_QUERY)) {
            $this->query = \strval($input->getOption('query'));
            Json::decode($this->query, 'Invalid json query');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->conn->setAutoCommit(false);

        if (!$this->isContinue || $this->isCron) {
            $this->lock($this->contentType, $this->query, $this->isForce, $this->isCron, $this->ouuid);
        }

        $page = 0;
        $limit = 200;
        $paginator = $this->revisionRepository->findAllLockedRevisions($this->contentType, self::LOCK_BY, $page, $limit);

        $progress = $this->io->createProgressBar($paginator->count());
        $progress->start();

        $missingInIndex = false;
        if ($this->isMissing) {
            $missingInIndex = $this->contentTypeService->getIndex($this->contentType);
        }

        do {
            /** @var Revision $revision */
            foreach ($paginator as $revision) {
                $revisionId = Type::integer($revision->getId());

                if ($missingInIndex) {
                    try {
                        $this->searchService->getDocument($this->contentType, $revision->giveOuuid());
                        $this->revisionRepository->unlockRevision($revisionId);
                        $progress->advance();
                        continue;
                    } catch (NotFoundException) {
                    }
                }

                $newRevision = $revision->convertToDraft();
                $revisionType = $this->formFactory->create(RevisionType::class, $newRevision, [
                    'migration' => true,
                    'content_type' => $this->contentType,
                ]);

                if ($this->isDeep) {
                    $newRevision->setRawData([]);
                    $viewData = $this->dataService->getSubmitData($revisionType->get('data'));
                    $revisionType->submit(['data' => $viewData]);
                }

                $notifications = [];
                foreach ($revision->getNotifications() as $notification) {
                    if (Notification::PENDING !== $notification->getStatus()) {
                        continue;
                    }
                    $notification->setStatus(Notification::IN_TRANSIT);
                    $notifications[] = $notification;
                }

                $objectArray = $newRevision->getRawData();

                $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $this->contentType, $this->contentType->getName(), $newRevision->getOuuid(), true);
                $newRevision->setRawData($objectArray);

                $this->dataService->sign($revision);
                $this->dataService->sign($newRevision);

                if ($this->isChanged && $revision->getHash() === $newRevision->getHash()) {
                    $this->revisionRepository->unlockRevision($revisionId);
                    $progress->advance();
                    continue;
                }

                $revision->close(new \DateTime('now'));
                $newRevision->setDraft(false);

                $newRevision->setFinalizedBy(self::LOCK_BY);
                $newRevision->setRawDataFinalizedBy(self::LOCK_BY);
                $this->dataService->sign($newRevision);

                $this->em->persist($revision);
                $this->em->persist($newRevision);
                $this->em->flush();

                $this->indexService->indexRevision($newRevision);

                foreach ($notifications as $notification) {
                    if (Notification::IN_TRANSIT !== $notification->getStatus()) {
                        continue;
                    }
                    $notification->setStatus(Notification::PENDING);
                    $notification->setRevision($newRevision);
                    $this->em->persist($notification);
                    $this->em->flush($notification);
                }

                if (!$this->isAlign) {
                    foreach ($revision->getEnvironments() as $environment) {
                        $this->logger->info('published to {env}', ['env' => $environment->getName()]);
                        $this->publishService->publish($newRevision, $environment, self::LOCK_BY);
                    }
                }

                $this->revisionRepository->unlockRevision($revisionId);
                $this->revisionRepository->unlockRevision(Type::integer($newRevision->getId()));

                $progress->advance();
            }

            if ($this->conn->isTransactionActive()) {
                $this->em->commit();
            }

            $this->em->clear();

            $paginator = $this->revisionRepository->findAllLockedRevisions($this->contentType, self::LOCK_BY, $page, $limit);
            $iterator = $paginator->getIterator();
        } while ($iterator instanceof \ArrayIterator && $iterator->count());

        $progress->finish();
        $this->io->newLine();

        $this->conn->setAutoCommit(true);

        return self::EXECUTE_SUCCESS;
    }

    private function lock(ContentType $contentType, string $query, bool $force = false, bool $ifEmpty = false, ?string $ouuid = null): void
    {
        $this->runCommand(
            command: Commands::CONTENT_TYPE_LOCK,
            args: [
                LockCommand::ARGUMENT_CONTENT_TYPE => $contentType->getName(),
                LockCommand::ARGUMENT_TIME => '+1day',
            ],
            options: [
                LockCommand::OPTION_USER => self::LOCK_BY,
                LockCommand::OPTION_FORCE => $force,
                LockCommand::OPTION_IF_EMPTY => $ifEmpty,
                LockCommand::OPTION_OUUID => $ouuid,
                LockCommand::OPTION_QUERY => $query,
            ]
        );
    }
}
