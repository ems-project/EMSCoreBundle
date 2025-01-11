<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Notification;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: Commands::NOTIFICATION_BULK_ACTION,
    description: 'Bulk all notifications actions for the passed query.',
    hidden: false,
    aliases: ['ems:notification:bulk-action']
)]
final class BulkActionCommand extends Command
{
    private const string CONTENT_TYPE_NAME = 'contentTypeName';
    private SymfonyStyle $io;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly ElasticaService $elasticaService,
        private readonly RevisionService $revisionService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::CONTENT_TYPE_NAME, InputArgument::REQUIRED, 'Content type name')
            ->addArgument('actionName', InputArgument::REQUIRED, 'Notification action name')
            ->addArgument('query', InputArgument::REQUIRED, 'ES query')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'notification user', 'ems')
            ->addOption('environment', null, InputOption::VALUE_REQUIRED, 'EMS environment')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do the bulk');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Bulk action notifications');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contentTypeName = \strval($input->getArgument(self::CONTENT_TYPE_NAME));
        $contentType = $this->contentTypeService->giveByName($contentTypeName);
        $actionName = \strval($input->getArgument('actionName'));
        $action = $contentType->getActionByName($actionName);
        if (null === $action) {
            throw new \Exception(\sprintf('No notification action found with name %d', $actionName));
        }

        $rawQuery = \strval($input->getArgument('query'));
        try {
            $query = Json::decode($rawQuery);
        } catch (\Throwable) {
            throw new \RuntimeException(\sprintf('Invalid json query %s', $rawQuery));
        }

        $inputEnvironment = $input->getOption('environment');
        $environmentName = $inputEnvironment ? \strval($inputEnvironment) : null;
        if (null !== $environmentName) {
            $environment = $this->environmentService->giveByName($environmentName);
        } else {
            $environment = $contentType->giveEnvironment();
        }

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $environment->getAlias(),
            '_source' => false,
            'body' => $query,
        ]);
        $search->setContentTypes([$contentTypeName]);

        $countDocuments = $this->elasticaService->count($search);
        $this->io->block(\vsprintf('Found %s documents for notification action "%s" in %s', [
            $countDocuments,
            $action->getName(),
            $environment->getName(),
        ]));

        if (true !== $input->getOption('force')) {
            $this->io->caution('For executing the bulk please rerun with --force');

            return 0;
        }

        $username = \strval($input->getOption('username'));
        $countSend = 0;
        $progress = $this->io->createProgressBar($countDocuments);
        $progress->start();

        foreach ($this->searchDocuments($search) as $document) {
            if (null === $revision = $this->revisionService->getCurrentRevisionForDocument($document)) {
                $this->io->warning(\sprintf('Could not find revision for ouuid "%s"', $document->getId()));
                continue;
            }
            $action = $revision->giveContentType()->getActionByName($actionName);
            if (null === $action) {
                throw new \RuntimeException(\sprintf('Action %s not found for content type %s', $actionName, $revision->giveContentType()->getSingularName()));
            }
            $added = $this->notificationService->addNotification($action, $revision, $environment, $username);

            if ($added) {
                ++$countSend;
            }
        }

        $progress->finish();

        $this->io->newLine(2);
        $this->io->success(\sprintf('Created %d new notification with username "%s"', $countSend, $username));

        return 0;
    }

    /**
     * @return \Generator|DocumentInterface[]
     */
    private function searchDocuments(Search $search): \Generator
    {
        foreach ($this->elasticaService->scroll($search) as $resultSet) {
            foreach ($resultSet as $result) {
                yield Document::fromResult($result);
            }
        }
    }
}
