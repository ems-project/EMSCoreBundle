<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    public function __invoke(string $dashboardName, Request $request): Response
    {
        $format = $request->query->get('format', 'html');
        $dashboard = $this->dashboardManager->getByName($dashboardName);

        try {
            $content = $this->twig->render("@$this->templateNamespace/dashboard/browser/dashboard-browser-modal.$format.twig", [
                'dashboard' => $dashboard,
            ]);
            $success = true;
        } catch (\Throwable $e) {
            $content = $this->twig->render("@$this->templateNamespace/dashboard/browser/dashboard-browser-modal-error.$format.twig", [
                'exception' => $e,
                'dashboard' => $dashboard,
            ]);
            $success = false;
        }

        if ($format === 'json') {
            dump('json');
            return new JsonResponse([
                'success' => $success,
                'content' => $content,
            ], $success ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response($content, $success ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
