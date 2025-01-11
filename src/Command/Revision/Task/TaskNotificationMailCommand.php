<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision\Task;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Task\TaskMailer;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TaskNotificationMailCommand extends AbstractCommand
{
    private string $subject;
    private bool $includeTasksManagers;
    private int $limit;
    private ?\DateTimeImmutable $deadlineStart = null;
    private ?\DateTimeImmutable $deadlineEnd = null;

    protected static $defaultName = Commands::REVISION_TASK_NOTIFICATION_MAIL;
    private const string OPTION_SUBJECT = 'subject';
    private const string OPTION_INCLUDE_TASK_MANAGERS = 'include-task-managers';
    private const string OPTION_DEADLINE_START = 'deadline-start';
    private const string OPTION_DEADLINE_END = 'deadline-end';
    private const string OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly TaskMailer $taskMailer,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDescription('Send notification mail for tasks')
            ->addOption(self::OPTION_SUBJECT, null, InputOption::VALUE_REQUIRED, 'Set mail subject', 'notification tasks')
            ->addOption(self::OPTION_DEADLINE_START, null, InputOption::VALUE_REQUIRED, 'Start deadline from now "-1 days"')
            ->addOption(self::OPTION_DEADLINE_END, null, InputOption::VALUE_REQUIRED, 'End deadline from now "+1 days"')
            ->addOption(self::OPTION_INCLUDE_TASK_MANAGERS, null, InputOption::VALUE_NONE, 'Include task admins/managers')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'limit the results inside mail', 10)
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Revision - Task notification mail');

        $this->subject = $this->getOptionString(self::OPTION_SUBJECT);
        $this->includeTasksManagers = $this->getOptionBool(self::OPTION_INCLUDE_TASK_MANAGERS);
        $this->limit = $this->getOptionInt(self::OPTION_LIMIT);

        if ($deadlineStart = $this->getOptionStringNull(self::OPTION_DEADLINE_START)) {
            $this->deadlineStart = (new \DateTimeImmutable())->modify($deadlineStart);
        }
        if ($deadlineEnd = $this->getOptionStringNull(self::OPTION_DEADLINE_END)) {
            $this->deadlineEnd = (new \DateTimeImmutable())->modify($deadlineEnd);
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $revisionsByReceiver = [];
        $revisionsWithCurrentTask = $this->taskManager->getRevisionsWithCurrentTask(
            deadlineStart: $this->deadlineStart,
            deadlineEnd: $this->deadlineEnd
        );
        $taskManagers = $this->taskManager->getTaskManagers();

        foreach ($revisionsWithCurrentTask as $revision) {
            $task = $revision->getTaskCurrent();
            $taskStatus = TaskStatus::from($task->getStatus());

            if (TaskStatus::PROGRESS === $taskStatus || TaskStatus::REJECTED === $taskStatus) {
                $revisionsByReceiver[$task->getAssignee()][] = $revision;
            }
            if (TaskStatus::COMPLETED === $taskStatus) {
                $revisionsByReceiver[$task->getCreatedBy()][] = $revision;
            }

            if ($this->includeTasksManagers) {
                foreach ($taskManagers as $taskManager) {
                    $revisionsByReceiver[$taskManager->getUsername()][] = $revision;
                }
            }
        }

        foreach ($revisionsByReceiver as $receiver => $revisions) {
            $this->taskMailer->sendNotificationMail($receiver, $this->subject, $revisions, $this->limit);
        }

        return self::EXECUTE_SUCCESS;
    }
}
