<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class DashboardBrowserController
{
    public function __construct(
        private readonly DashboardManager $dashboardManager,
        private readonly Environment $twig,
        private readonly string $templateNamespace,
    ) {
    }

    public function __invoke(string $dashboardName): Response
    {
        $dashboard = $this->dashboardManager->getByName($dashboardName);
        $response = new Response();

        try {
            $response->setContent($this->twig->render("@$this->templateNamespace/dashboard/browser/dashboard-browser-modal.html.twig", [
                'dashboard' => $dashboard,
            ]));
        } catch (\Throwable $e) {
            $response->setContent($this->twig->render("@$this->templateNamespace/dashboard/browser/dashboard-browser-modal-error.html.twig", [
                'exception' => $e,
                'dashboard' => $dashboard,
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
