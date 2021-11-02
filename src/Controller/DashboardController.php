<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DashboardController extends AbstractController
{
    private TaskManager $taskManager;
    private DashboardManager $dashboardManager;
    private DashboardService $dashboardService;

    public function __construct(TaskManager $taskManager, DashboardManager $dashboardManager, DashboardService $dashboardService)
    {
        $this->taskManager = $taskManager;
        $this->dashboardManager = $dashboardManager;
        $this->dashboardService = $dashboardService;
    }

    public function landing(): RedirectResponse
    {
        if ($this->taskManager->hasDashboard()) {
            return $this->redirectToRoute('ems_core_task_dashboard');
        }

        return $this->redirectToRoute('notifications.inbox');
    }

    public function dashboard(string $name): Response
    {
        $dashboard = $this->dashboardManager->getByName($name);
        if (!$this->isGranted($dashboard->getRole())) {
            throw new AccessDeniedHttpException();
        }
        $dashboardService = $this->dashboardService->get($dashboard->getType());

        return $dashboardService->getResponse($dashboard);
    }
}
