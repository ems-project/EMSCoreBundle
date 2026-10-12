<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function Symfony\Component\Translation\t;

final class Matomo implements DashboardInterface
{
    public function __construct(private readonly Environment $twig, private readonly string $templateNamespace)
    {
    }

    #[\Override]
    public function getResponse(Dashboard $dashboard, Navigation $breadcrumb): Response
    {
        $response = new Response();
        try {
            $response->setContent($this->twig->render(\sprintf('@%s/dashboard/services/matomo.html.twig', $this->templateNamespace), [
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
                'matomoUrl' => $this->buildEmbedUrl($dashboard->getOptions()),
                'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
                'breadcrumb' => $breadcrumb,
            ]));
        } catch (\Throwable $throwable) {
            $response->setContent($this->twig->render(\sprintf('@%s/dashboard/services/error.html.twig', $this->templateNamespace), [
                'exception' => $throwable,
                'dashboard' => $dashboard,
                'options' => $dashboard->getOptions(),
                'title' => t('core.dashboard.exception.title', [], 'emsco-core'),
                'subTitle' => t('type.title_sub', ['type' => 'dashboard'], 'emsco-core'),
                'breadcrumb' => $breadcrumb,
            ]));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $response;
    }

    private function buildEmbedUrl(DashboardOptions $options): string
    {
        $matomoUrl = $options->getNullableString(DashboardOptions::MATOMO_URL);
        if (null === $matomoUrl || '' === \trim($matomoUrl)) {
            throw new \InvalidArgumentException('The Matomo URL is required.');
        }

        $parts = \parse_url($matomoUrl);
        if (!\is_array($parts) || !\in_array(\strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || !\is_string($parts['host'] ?? null)) {
            throw new \InvalidArgumentException('The Matomo URL must be an absolute HTTP(S) URL.');
        }

        $path = \rtrim($parts['path'] ?? '', '/');
        if (!\str_ends_with($path, '/index.php')) {
            $path .= '/index.php';
        }

        $scheme = (string) ($parts['scheme'] ?? '');
        $endpoint = \sprintf('%s://%s%s', $scheme, $parts['host'], $path);
        if (isset($parts['port'])) {
            $endpoint = \sprintf('%s://%s:%d%s', $scheme, $parts['host'], $parts['port'], $path);
        }

        $siteId = $options->getInteger(DashboardOptions::MATOMO_SITE_ID, 1);
        if ($siteId < 1) {
            throw new \InvalidArgumentException('The Matomo site ID must be greater than zero.');
        }

        $query = [
            'module' => 'Widgetize',
            'action' => 'iframe',
            'moduleToWidgetize' => 'Dashboard',
            'actionToWidgetize' => 'index',
            'idSite' => $siteId,
            'period' => 'day',
            'date' => 'yesterday',
        ];
        $tokenAuth = $options->getNullableString(DashboardOptions::MATOMO_TOKEN_AUTH);
        if (null === $tokenAuth || '' === \trim($tokenAuth)) {
            throw new \InvalidArgumentException('A Matomo view-only authentication token is required.');
        }
        $query['token_auth'] = $tokenAuth;

        return $endpoint.'?'.\http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
