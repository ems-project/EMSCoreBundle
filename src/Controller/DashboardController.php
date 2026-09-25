<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DashboardController extends AbstractController
{
    public function __construct(private readonly DashboardManager $dashboardManager, private readonly DashboardService $dashboardService)
    {
    }

    public function quickSearch(): Response
    {
        $dashboard = $this->dashboardManager->getDefinition(Dashboard::DEFINITION_QUICK_SEARCH);
        if (null === $dashboard) {
            return $this->redirectToRoute('notifications.inbox');
        }
        if (!$this->isGranted($dashboard->getRole())) {
            throw new AccessDeniedHttpException();
        }
        $dashboardService = $this->dashboardService->get($dashboard->getType());

        return $dashboardService->getResponse($dashboard, $this->breadcrumb($dashboard));
    }

    public function dashboard(?string $name = null): Response
    {
        if (null === $name) {
            $dashboard = $this->dashboardManager->getDefinition(Dashboard::DEFINITION_LANDING_PAGE);
        } else {
            $dashboard = $this->dashboardManager->getByName($name);
        }
        if (null === $dashboard) {
            return $this->redirectToRoute('notifications.inbox');
        }
        if (!$this->isGranted($dashboard->getRole())) {
            throw new AccessDeniedHttpException();
        }
        $dashboardService = $this->dashboardService->get($dashboard->getType());
        $breadcrumb = $this->breadcrumb($dashboard);

        return $dashboardService->getResponse($dashboard, $breadcrumb);
    }

    private function breadcrumb(Dashboard $dashboard): Navigation
    {
        $route = match ($dashboard->getDefinition()) {
            Dashboard::DEFINITION_QUICK_SEARCH => 'ems_search',
            Dashboard::DEFINITION_LANDING_PAGE => 'ems_homepage',
            default => Routes::DASHBOARD
        };
        $params = Routes::DASHBOARD == $route ? ['name' => $dashboard->getName()] : [];

        return Navigation::dashboards()->add(
            text: $dashboard->getLabel(),
            icon: $dashboard->getIcon(),
            route: 'emsco_dashboard_admin_index',
            routeParams: $params,
        );
    }
}
