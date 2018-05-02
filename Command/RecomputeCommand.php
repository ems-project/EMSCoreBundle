<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
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
use Symfony\Component\HttpFoundation\Session\Session;

class RecomputeCommand extends EmsCommand
{
    /**
     * @var EntityManager
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
        Session $session
    ) {
        parent::__construct($logger, $client, $session);

        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->publishService = $publishService;

        $em = $doctrine->getManager();
        $this->em = $em;
        $this->contentTypeRepository = $em->getRepository(ContentType::class);
        $this->revisionRepository = $em->getRepository(Revision::class);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ems:contenttype:recompute')
            ->setDescription('Recompute a content type')
            ->addArgument('contentType', InputArgument::REQUIRED, 'content type to recompute')
            ->addOption('force', null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'continue a recompute')
            ->addOption('keep-align', null , InputOption::VALUE_NONE, 'keep the revisions aligned to all already aligned environments')
            ->addOption('cron', null , InputOption::VALUE_NONE, 'optimized for automated recurring recompute calls, tries --continue, when no locks are found for user runs command without --continue')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'recompute a specific id')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->em->getConnection()->setAutoCommit(false);

        $io = new SymfonyStyle($input, $output);

        /** @var $contentType ContentType */
        if (null === $contentType = $this->contentTypeRepository->findOneBy(['name' => $input->getArgument('contentType')])) {
            throw new \RuntimeException('invalid content type');
        }

        if (!$input->getOption('continue') || $input->getOption('cron')) {
            $this->lock($output, $contentType, $input->getOption('force'), $input->getOption('cron'), $input->getOption('id'));
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

        do {
            foreach ($paginator as $revision) {
                /** @var Revision $revision */
                $newRevision = $revision->convertToDraft();

                $revisionType->setData($newRevision);
                $objectArray = $newRevision->getRawData();

                //@todo maybe improve the data binding like the migration?

                $this->dataService->propagateDataToComputedField($revisionType->get('data'), $objectArray, $contentType, $contentType->getName(), $newRevision->getOuuid(), true);
                $newRevision->setRawData($objectArray);

                $revision->close(new \DateTime('now'));
                $newRevision->setDraft(false);

                $this->em->persist($revision);
                $this->em->persist($newRevision);
                $this->em->flush();

                if ($input->getOption('keep-align')) {
                    foreach ($revision->getEnvironments() as $environment) {
                        $this->logger->info('published to {env}', ['env' => $environment->getName()]);
                        $this->publishService->publish($newRevision, $environment, true);
                    }
                }

                $this->revisionRepository->unlockRevision($revision->getId());
                $this->revisionRepository->unlockRevision($newRevision->getId());

                $this->em->commit();

                $progress->advance();
            }

            $paginator = $this->revisionRepository->findAllLockedRevisions($contentType, self::LOCK_BY, $page, $limit);
        } while ($paginator->getIterator()->count());

        $progress->finish();
    }

    /**
     * @param OutputInterface $output
     * @param ContentType     $contentType
     * @param bool            $force
     */
    private function lock(OutputInterface $output, ContentType $contentType, $force = false, $ifEmpty = false, $id = false)
    {
        $command = $this->getApplication()->find('ems:contenttype:lock');
        $arguments = [
            'command'     => 'ems:contenttype:lock',
            'contentType' => $contentType->getName(),
            'time'        => '+1day',
            '--user'      => self::LOCK_BY,
            '--force'     => $force,
            '--if-empty'  => $ifEmpty,
            '--id'        => $id
        ];

        $command->run(new ArrayInput($arguments), $output);
    }
}