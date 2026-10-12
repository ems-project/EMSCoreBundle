<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\Dashboard\Services\Matomo;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class MatomoTest extends TestCase
{
    public function testRenderBuildsMatomoEmbedUrl(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                '@EMSAdminUI/dashboard/services/matomo.html.twig',
                $this->callback(function (array $context): bool {
                    $this->assertSame(
                        'https://analytics.example.com/matomo/index.php?module=Widgetize&action=iframe&moduleToWidgetize=Dashboard&actionToWidgetize=index&idSite=3&period=day&date=yesterday&token_auth=read-only-token',
                        $context['matomoUrl'],
                    );

                    return true;
                }),
            )
            ->willReturn('<iframe></iframe>');

        $dashboard = new Dashboard();
        $dashboard->setOptions(new DashboardOptions([
            DashboardOptions::MATOMO_URL => 'https://analytics.example.com/matomo',
            DashboardOptions::MATOMO_SITE_ID => 3,
            DashboardOptions::MATOMO_TOKEN_AUTH => 'read-only-token',
        ]));

        $response = new Matomo($twig, 'EMSAdminUI')->getResponse($dashboard, Navigation::dashboards());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<iframe></iframe>', $response->getContent());
    }

    public function testInvalidMatomoUrlIsRenderedAsDashboardError(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(static function (string $template): string {
                return $template;
            });

        $dashboard = new Dashboard();
        $dashboard->setOptions(new DashboardOptions([
            DashboardOptions::MATOMO_URL => 'not-an-absolute-url',
        ]));

        $response = new Matomo($twig, 'EMSAdminUI')->getResponse($dashboard, Navigation::dashboards());

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('@EMSAdminUI/dashboard/services/error.html.twig', $response->getContent());
    }
}
