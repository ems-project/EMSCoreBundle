<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class Export implements DashboardInterface
{
    public function __construct(private readonly Environment $twig, private readonly string $templateNamespace)
    {
    }

    #[\Override]
    public function getResponse(Dashboard $dashboard): Response
    {
        $response = new Response();
        try {
            $body = $dashboard->getOption(DashboardOptions::BODY) ?? '';
            $template = $this->twig->createTemplate($body, \sprintf('Body template for dashboard %s', $dashboard->getName()));
            $response->setContent($this->twig->render($template, [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));

            $filename = $dashboard->getOption(DashboardOptions::FILENAME) ?? 'filename';
            $disposition = $dashboard->getOption(DashboardOptions::FILE_DISPOSITION);
            $mimetype = $dashboard->getOption(DashboardOptions::MIMETYPE);

            if (\is_string($mimetype)) {
                $response->headers->set('Content-Type', $mimetype);
            }

            if ($disposition) {
                $disposition = $response->headers->makeDisposition($disposition, $filename);
                $response->headers->set('Content-Disposition', $disposition);
            }
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
