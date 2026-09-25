<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class Export implements DashboardInterface
{
    public function __construct(private readonly Environment $twig, private readonly string $templateNamespace)
    {
    }

    #[\Override]
    public function getResponse(Dashboard $dashboard, Navigation $breadcrumb): Response
    {
        $response = new Response();
        try {
            $body = $dashboard->getNullableStringOption(DashboardOptions::BODY) ?? '';
            $template = $this->twig->createTemplate($body, \sprintf('Body template for dashboard %s', $dashboard->getName()));
            $response->setContent($this->twig->render($template, [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));

            $filename = $dashboard->getNullableStringOption(DashboardOptions::FILENAME) ?? 'filename';
            $disposition = $dashboard->getNullableStringOption(DashboardOptions::FILE_DISPOSITION);
            $mimetype = $dashboard->getNullableStringOption(DashboardOptions::MIMETYPE);

            if (\is_string($mimetype)) {
                $response->headers->set('Content-Type', $mimetype);
            }

            if ($disposition) {
                $disposition = $response->headers->makeDisposition($disposition, $filename);
                $response->headers->set('Content-Disposition', $disposition);
            }
        } catch (\Throwable $throwable) {
            $response->setContent($this->twig->render(\sprintf('@%s/dashboard/services/error.html.twig', $this->templateNamespace), [
                'exception' => $throwable,
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }
}
