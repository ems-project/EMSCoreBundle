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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class RecomputeCommand extends EmsCommand
{
    /**
     * @var Registry
     */
    private $doctrine;

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

        $this->doctrine = $doctrine;
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
        $this->publishService = $publishService;
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
            ->addOption('keep-align', null , InputOption::VALUE_NONE, 'keep the revisions aligned to all already aligned environments')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new ConsoleLogger($output);
        $contentTypeName = $input->getArgument('contentType');

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);
        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $em->getRepository(Revision::class);

        /** @var ContentType $contentType */
        $contentType = $contentTypeRepository->findOneBy(['name' => $contentTypeName]);

        if(!$contentType) {
            $output->writeln("<error>Content type ".$contentTypeName." not found</error>");
            exit;
        }

        $page = 0;
        $revisionPaginator = $revisionRepository->findAllActiveByContentType($contentType, $page);
        $this->logger->info('found {count} revisions', ['count' => $revisionPaginator->getIterator()->count()]);

        do {
            foreach ($revisionPaginator as $revision) {
                /** @var \EMS\CoreBundle\Entity\Revision $revision */

                $newRevision = $this->save($contentType->getName(), $revision->getOuuid());

                if ($input->getOption('keep-align')) {
                    foreach ($revision->getEnvironments() as $environment) {
                        $this->logger->info('published to {env}', ['env' => $environment->getName()]);
                        $this->publishService->publish($newRevision, $environment, true);
                    }
                }
            }

            ++$page;
            $revisionPaginator = $revisionRepository->findAllActiveByContentType($contentType, $page);
        } while ($revisionPaginator->getIterator()->count());
    }

    /**
     * @param string $contentType
     * @param string $ouuid
     *
     * @return \EMS\CoreBundle\Entity\Revision
     */
    private function save($contentType, $ouuid)
    {
        $revision = $this->dataService->initNewDraft($contentType, $ouuid, null, 'cron_job');
        $rawData = $revision->getRawData();

        if( $revision->getDatafield() == NULL){
            $this->dataService->loadDataStructure($revision);
        }

        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision);
        $form = $builder->getForm();

        $revision->setRawData($rawData);

        $this->logger->info('finalize {ouuid}', ['ouuid' => $ouuid]);

        $this->dataService->finalizeDraft($revision, $form, 'cron_job');

        return $revision;
    }
}