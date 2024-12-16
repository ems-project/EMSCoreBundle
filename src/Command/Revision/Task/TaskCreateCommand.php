<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision\Task;

use Elastica\Document;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\DateTime;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISION_TASK_CREATE,
    hidden: false
)]
final class TaskCreateCommand extends AbstractCommand
{
    private Environment $environment;
    /** @var array<mixed> */
    private array $task;
    private string $searchQuery;

    private string $requester;
    private ?string $fieldAssignee = null;
    private ?string $fieldDeadline = null;
    private ?string $notPublished = null;

    private const DEFAULT_REQUESTER = 'SYSTEM_TASK_MANAGER';

    public const ARGUMENT_ENVIRONMENT = 'environment';
    public const OPTION_TASK = 'task';
    public const OPTION_REQUESTER = 'requester';
    public const OPTION_FIELD_ASSIGNEE = 'field-assignee';
    public const OPTION_FIELD_DEADLINE = 'field-deadline';
    public const OPTION_NOT_PUBLISHED = 'not-published';
    public const OPTION_SCROLL_SIZE = 'scroll-size';
    public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    public const OPTION_SEARCH_QUERY = 'search-query';

    public function __construct(
        private readonly RevisionSearcher $revisionSearcher,
        private readonly EnvironmentService $environmentService,
        private readonly UserService $userService,
        private readonly TaskManager $taskManager,
        private readonly string $coreDateFormat,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_ENVIRONMENT, InputArgument::REQUIRED)
            ->addOption(self::OPTION_TASK, null, InputOption::VALUE_REQUIRED, '{\"title\":\"title\",\"assignee\":\"username\",\"description\":\"optional\"}')
            ->addOption(self::OPTION_FIELD_ASSIGNEE, null, InputOption::VALUE_REQUIRED, 'assignee field in es document')
            ->addOption(self::OPTION_REQUESTER, null, InputOption::VALUE_REQUIRED, 'requester')
            ->addOption(self::OPTION_FIELD_DEADLINE, null, InputOption::VALUE_REQUIRED, 'deadline field in es document')
            ->addOption(self::OPTION_NOT_PUBLISHED, null, InputOption::VALUE_REQUIRED, 'only for revisions not published in this environment')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', '{}')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Revision - Task create');

        $environmentName = $this->getArgumentString('environment');
        $this->environment = $this->environmentService->giveByName($environmentName);

        $this->task = Json::decode($this->getOptionString(self::OPTION_TASK));
        $this->requester = $this->getOptionString(self::OPTION_REQUESTER, self::DEFAULT_REQUESTER);
        $this->fieldAssignee = $this->getOptionStringNull(self::OPTION_FIELD_ASSIGNEE);
        $this->fieldDeadline = $this->getOptionStringNull(self::OPTION_FIELD_DEADLINE);
        $this->notPublished = $this->getOptionStringNull(self::OPTION_NOT_PUBLISHED);

        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $search = $this->revisionSearcher->create($this->environment, $this->searchQuery, [], true);

        $this->io->comment(\sprintf('Found %s hits', $search->getTotal()));
        $this->io->progressStart($search->getTotal());

        foreach ($this->revisionSearcher->search($this->environment, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, self::DEFAULT_REQUESTER);

            foreach ($revisions->transaction() as $revision) {
                if (null !== $document = $revisions->getDocument($revision)) {
                    $this->createTask($revision, $document);
                }
                $this->io->progressAdvance();
            }

            $this->revisionSearcher->unlock($revisions);
        }

        $this->io->progressFinish();

        return self::EXECUTE_SUCCESS;
    }

    private function createTask(Revision $revision, Document $document): void
    {
        if (!$revision->giveContentType()->tasksEnabled()) {
            $this->io->warning(\sprintf('Skipping revision %s tasks not enabled', $revision));

            return;
        }

        if ($revision->hasEndTime()
            || null !== $revision->getVersionDate('to')
            || $revision->hasTasks(false)) {
            return;
        }

        if ($this->notPublished && $revision->isPublished($this->notPublished)) {
            return;
        }

        $taskDTO = new TaskDTO($this->coreDateFormat);
        $taskDTO->title = $this->task['title'];
        $taskDTO->assignee = $this->task['assignee'];
        $taskDTO->description = $this->task['description'] ?? null;
        $taskDTO->delay = 1;

        if (null !== $this->fieldAssignee && $document->has($this->fieldAssignee)) {
            $assignee = $document->get($this->fieldAssignee);
            $user = $this->userService->searchUser($assignee);
            if (null !== $user) {
                $taskDTO->assignee = $user->getUsername();
            }
        }

        if (null !== $this->fieldDeadline && $document->has($this->fieldDeadline)) {
            $taskDTO->deadline = DateTime::create($document->get($this->fieldDeadline));
        }

        $this->taskManager->taskCreateFromRevision($taskDTO, $revision, $this->requester);
    }
}
