<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function Symfony\Component\Translation\t;

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
                'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
                'breadcrumb' => Navigation::dashboards()->add(
                    text: $dashboard->getLabel(),
                    icon: $dashboard->getIcon(),
                ),
            ]));
        } catch (\Throwable $e) {
            $response->setContent($this->twig->render("@$this->templateNamespace/dashboard/services/error.html.twig", [
                'exception' => $e,
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
                'title' => t('core.dashboard.exception.title', [], 'emsco-core'),
                'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
                'breadcrumb' => Navigation::dashboards()->add(
                    text: $dashboard->getLabel(),
                    icon: $dashboard->getIcon(),
                ),
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
