<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class DashboardController extends AbstractController
{
    private TaskManager $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function dashboard(): RedirectResponse
    {
        if ($this->taskManager->hasDashboard()) {
            return $this->redirectToRoute('ems_core_task_dashboard');
        }

        return $this->redirectToRoute('notifications.inbox');
    }
}
