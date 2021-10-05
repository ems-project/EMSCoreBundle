<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Notification;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class NotificationBulkActionCommand extends Command
{
    protected static $defaultName = Commands::NOTIFICATION_BULK_ACTION;

    private NotificationService $notificationService;
    private EnvironmentService $environmentService;
    private ElasticaService $elasticaService;
    private RevisionService $revisionService;
    private SymfonyStyle $io;

    public function __construct(
        NotificationService $notificationService,
        EnvironmentService $environmentService,
        ElasticaService $elasticaService,
        RevisionService $revisionService
    ) {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->environmentService = $environmentService;
        $this->elasticaService = $elasticaService;
        $this->revisionService = $revisionService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Bulk all notifications actions for the passed query')
            ->addArgument('actionId', InputArgument::REQUIRED, 'Notification action id')
            ->addArgument('query', InputArgument::REQUIRED, 'ES query')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'notification user', 'ems')
            ->addOption('environment', null, InputOption::VALUE_REQUIRED, 'EMS environment')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do the bulk');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Bulk action notifications');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionId = \intval($input->getArgument('actionId'));
        if (null === $action = $this->notificationService->getAction($actionId)) {
            throw new \Exception(\sprintf('No notification action found with id %d', $actionId));
        }

        $rawQuery = \strval($input->getArgument('query'));
        $query = \json_decode($rawQuery, true);
        if (\json_last_error() > 0) {
            throw new \RuntimeException(\sprintf('Invalid json query %s', $rawQuery));
        }

        $inputEnvironment = $input->getOption('environment');
        $environmentName = $inputEnvironment ? \strval($inputEnvironment) : null;
        if (null !== $environmentName) {
            $environment = $this->environmentService->giveByName($environmentName);
        } else {
            $environment = $action->giveContentType()->giveEnvironment();
        }

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $environment->getAlias(),
            '_source' => false,
            'body' => $query,
        ]);

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

            $added = $this->notificationService->addNotification($actionId, $revision, $environment, $username);

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
                if ($result) {
                    yield Document::fromResult($result);
                }
            }
        }
    }
}
