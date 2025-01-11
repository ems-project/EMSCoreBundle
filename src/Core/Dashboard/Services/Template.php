<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class Template implements DashboardInterface
{
    public function __construct(private readonly Environment $twig, private readonly string $templateNamespace)
    {
    }

    #[\Override]
    public function getResponse(Dashboard $dashboard): Response
    {
        $response = new Response();
        try {
            $response->setContent($this->twig->render("@$this->templateNamespace/dashboard/services/template.html.twig", [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));
        } catch (\Throwable $e) {
            $response->setContent($this->twig->render("@$this->templateNamespace/dashboard/services/error.html.twig", [
                'exception' => $e,
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
