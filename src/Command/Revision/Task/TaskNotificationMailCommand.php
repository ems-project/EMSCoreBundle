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
    private ?\DateTimeInterface $deadline = null;

    protected static $defaultName = Commands::REVISION_TASK_NOTIFICATION_MAIL;
    private const OPTION_SUBJECT = 'subject';
    private const OPTION_INCLUDE_TASK_MANAGERS = 'include-task-managers';
    private const OPTION_DEADLINE = 'deadline';
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly TaskMailer $taskMailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Send notification mail for tasks')
            ->addOption(self::OPTION_SUBJECT, null, InputOption::VALUE_REQUIRED, 'Set mail subject', 'notification tasks')
            ->addOption(self::OPTION_DEADLINE, null, InputOption::VALUE_REQUIRED, 'Example "-1 days"')
            ->addOption(self::OPTION_INCLUDE_TASK_MANAGERS, null, InputOption::VALUE_NONE, 'Include task managers')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'limit the results inside mail', 10)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Revision - Task notification mail');

        $this->subject = $this->getOptionString(self::OPTION_SUBJECT);
        $this->includeTasksManagers = $this->getOptionBool(self::OPTION_INCLUDE_TASK_MANAGERS);
        $this->limit = $this->getOptionInt(self::OPTION_LIMIT);

        if ($deadline = $this->getOptionStringNull(self::OPTION_DEADLINE)) {
            $this->deadline = (new \DateTimeImmutable())->modify($deadline);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $revisionsByReceiver = [];
        $revisionsWithCurrentTask = $this->taskManager->getRevisionsWithCurrentTask($this->deadline);
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
