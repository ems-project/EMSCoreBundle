<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Routes;
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

    /**
     * @deprecated
     */
    public function landing(): RedirectResponse
    {
        @\trigger_error(\sprintf('Route ems_core_dashboard is deprecated, use %s instead', Routes::DASHBOARD), E_USER_DEPRECATED);

        return $this->landingDashboard();
    }

    public function dashboard(?string $name): Response
    {
        if (null === $name) {
            return $this->landingDashboard();
        }
        $dashboard = $this->dashboardManager->getByName($name);
        if (!$this->isGranted($dashboard->getRole())) {
            throw new AccessDeniedHttpException();
        }
        $dashboardService = $this->dashboardService->get($dashboard->getType());

        return $dashboardService->getResponse($dashboard);
    }

    private function landingDashboard(): RedirectResponse
    {
        $dashboard = $this->dashboardManager->getLandingPage();
        if (null !== $dashboard) {
            return $this->redirectToRoute(Routes::DASHBOARD, ['name' => $dashboard->getName()]);
        }

        return $this->redirectToRoute('notifications.inbox');
    }
}
