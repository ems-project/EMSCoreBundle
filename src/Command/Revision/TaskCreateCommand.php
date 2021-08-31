<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use Elastica\Document;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskCreateCommand extends AbstractCommand
{
    private EnvironmentService $environmentService;
    private ElasticaService $elasticaService;
    private RevisionService $revisionService;
    private UserService $userService;
    private TaskManager $taskManager;

    private Environment $environment;
    /** @var array<mixed> */
    private array $query;
    /** @var array<mixed> */
    private array $task;
    private int $bulkSize;

    private string $defaultOwner;
    private ?string $fieldAssignee = null;
    private ?string $fieldDeadline = null;
    private ?string $notPublished = null;

    private const USER = 'SYSTEM_TASK_MANAGER';
    protected static $defaultName = 'ems:revision:task:create';

    public function __construct(
        EnvironmentService $environmentService,
        ElasticaService $elasticaService,
        RevisionService $revisionService,
        UserService $userService,
        TaskManager $taskManager,
        int $batchSize)
    {
        parent::__construct();
        $this->environmentService = $environmentService;
        $this->elasticaService = $elasticaService;
        $this->revisionService = $revisionService;
        $this->userService = $userService;
        $this->taskManager = $taskManager;
        $this->bulkSize = $batchSize;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('environment', InputArgument::REQUIRED)
            ->addOption('query', null, InputOption::VALUE_REQUIRED, 'elasticSearch query')
            ->addOption('task', null, InputOption::VALUE_REQUIRED, '{\"title\":\"title\",\"assignee\":\"username\",\"description\":\"optional\"}')
            ->addOption('fieldAssignee', null, InputOption::VALUE_REQUIRED, 'assignee field in es document')
            ->addOption('fieldDeadline', null, InputOption::VALUE_REQUIRED, 'deadline field in es document')
            ->addOption('defaultOwner', null, InputOption::VALUE_REQUIRED, 'default owner username')
            ->addOption('notPublished', null, InputOption::VALUE_REQUIRED, 'only for revisions not published in this environment')
            ->addOption('bulkSize', null, InputOption::VALUE_REQUIRED, 'batch size', 'default_bulk_size')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Revision - Task create');

        $environmentName = $this->getArgumentString('environment');
        $this->environment = $this->environmentService->giveByName($environmentName);

        $this->query = Json::decode($this->getOptionString('query'));
        $this->task = Json::decode($this->getOptionString('task'));

        $this->defaultOwner = $this->getOptionString('defaultOwner');
        $this->fieldAssignee = $this->getOptionStringNull('fieldAssignee');
        $this->fieldDeadline = $this->getOptionStringNull('fieldDeadline');
        $this->notPublished = $this->getOptionStringNull('notPublished');

        $this->bulkSize = $this->getOptionIntNull('bulkSize') ?? $this->bulkSize;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $search = $this->createSearch();
        $scroll = $this->elasticaService->scroll($search);
        $total = $this->elasticaService->count($search);

        $this->io->comment(\sprintf('Found %s hits', $total));
        $progress = $this->io->createProgressBar($total);

        foreach ($scroll as $resultSet) {
            $documents = $resultSet->getDocuments();

            $revisions = $this->revisionService->searchByResultSet($resultSet);
            $this->revisionService->lockRevisions($revisions, self::USER);

            foreach ($revisions->batch() as $revision) {
                $documentsOuuid = \array_filter($documents, fn (Document $doc) => $doc->getId() === $revision->getOuuid());
                $document = \array_shift($documentsOuuid);
                if (!$document instanceof Document) {
                    continue;
                }

                $this->createTask($revision, $document);
                $progress->advance();
            }

            $this->revisionService->unlockRevisions($revisions);
        }

        return self::EXECUTE_SUCCESS;
    }

    private function createTask(Revision $revision, Document $document): void
    {
        if (!$revision->isTaskEnabled()) {
            $this->io->warning(\sprintf('Skipping revision %s tasks not enabled', $revision));

            return;
        }

        if ($revision->hasTasks(false)) {
            return;
        }

        if ($this->notPublished && $revision->isPublished($this->notPublished)) {
            return;
        }

        $owner = $revision->hasOwner() ? $revision->getOwner() : $this->defaultOwner;

        $taskDTO = new TaskDTO();
        $taskDTO->title = $this->task['title'];
        $taskDTO->assignee = $this->task['assignee'];
        $taskDTO->description = $this->task['description'] ?? null;

        if ($this->fieldAssignee && $document->has($this->fieldAssignee)) {
            $assignee = $document->get($this->fieldAssignee);
            if ($user = $this->userService->searchUser($assignee)) {
                $taskDTO->assignee = $user->getUsername();
            }
        }

        if ($this->fieldDeadline && $document->has($this->fieldDeadline)) {
            $deadline = DateTime::create($document->get($this->fieldDeadline));
            $taskDTO->deadline = $deadline->format('d/m/Y');
        }

        $this->taskManager->taskCreateFromRevision($taskDTO, $revision, $owner);
    }

    private function createSearch(): Search
    {
        $search = $this->elasticaService->convertElasticsearchBody(
            [$this->environment->getAlias()],
            [],
            ['query' => $this->query]
        );

        $search->setSize($this->bulkSize);

        return $search;
    }
}
