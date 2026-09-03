<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class TwigElementsController extends AbstractController
{
    final public const string ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';

    public function __construct(
        private readonly AssetExtractorService $assetExtractorService,
        private readonly ElasticaService $elasticaService,
        private readonly UserService $userService,
        private readonly JobService $jobService,
        private readonly DashboardManager $dashboardManager,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace,
        private readonly bool $groupFeature,
    ) {
    }

    public function sideMenu(): Response
    {
        $status = $this->elasticaService->getHealthStatus();
        if ('green' === $status) {
            $status = $this->getAssetExtractorStatus();
        }

        return $this->render(
            \sprintf('@%s/elements/side-menu.html.twig', $this->templateNamespace),
            [
                'status' => $status,
                'menu' => [
                    $this->userService->getSidebarMenu(),
                    $this->dashboardManager->getSidebarMenu(),
                    $this->contentTypeService->getContentTypeMenu(),
                    $this->getPublisherMenu(),
                    $this->getCrmMenu(),
                    $this->getUserAdminMenu(),
                    $this->getAdminMenu(),
                    $this->getOtherMenu(),
                ],
            ]
        );
    }

    public function jobs(string $username): Response
    {
        return $this->render(
            \sprintf('@%s/elements/jobs-list.html.twig', $this->templateNamespace),
            [
                'jobs' => $this->jobService->findByUser($username),
            ]
        );
    }

    private function getAssetExtractorStatus(): string
    {
        $cache = new FilesystemAdapter('', 60);
        $cachedStatus = $cache->getItem(self::ASSET_EXTRACTOR_STATUS_CACHE_ID);
        if ($cachedStatus->isHit()) {
            return $cachedStatus->get();
        }

        try {
            $status = 200 === $this->assetExtractorService->hello()['code'] ? 'green' : 'yellow';
        } catch (\Throwable) {
            $status = 'yellow';
        }
        $cachedStatus->set($status);
        $cache->save($cachedStatus);

        return $status;
    }

    private function getOtherMenu(): Menu
    {
        $menu = new Menu(t('sidebar-menu.other', [], 'emsco-core'));
        $menu->addChild(t('sidebar-menu.documentation', [], 'emsco-core'), 'fa fa-book', 'documentation')->setTranslation([]);

        return $menu;
    }

    private function getUserAdminMenu(): Menu
    {
        $menu = new Menu(t('sidebar-menu.user-management', [], 'emsco-core'));
        if (!$this->isGranted('ROLE_USER_MANAGEMENT')) {
            return $menu;
        }
        $menu->addChild(t('key.users', [], 'emsco-core'), 'fa fa-users', Routes::USER_INDEX);
        if (!$this->groupFeature) {
            return $menu;
        }
        $menu->addChild(t('key.groups', [], 'emsco-core'), 'fa fa-list-ul', Routes::GROUP_INDEX);

        return $menu;
    }

    private function getAdminMenu(): Menu
    {
        $menu = new Menu(t('sidebar-menu.admin', [], 'emsco-core'));
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $menu;
        }
        $menu->addChild(t('key.content_types', [], 'emsco-core'), 'fa fa-sitemap', Routes::ADMIN_CONTENT_TYPE_INDEX)->setTranslation([]);
        $menu->addChild(t('key.forms', [], 'emsco-core'), 'fa fa-keyboard-o', Routes::FORM_ADMIN_INDEX)->setTranslation([]);

        $environmentMenu = $menu->addChild(
            label: t('key.environments', [], 'emsco-core'),
            icon: 'fa fa-database',
            route: Routes::ADMIN_ENVIRONMENT_INDEX
        );
        $environmentMenu->addChild(t('key.overview', [], 'emsco-core'), 'fa fa-list-ul', Routes::ADMIN_ENVIRONMENT_INDEX);
        $environmentMenu->addChild(t('key.unreferenced_aliases', [], 'emsco-core'), 'fa fa-chain', Routes::ADMIN_ELASTIC_UNREFERENCED_ALIASES);
        $environmentMenu->addChild(t('key.orphan_indexes', [], 'emsco-core'), 'fa fa-chain-broken', Routes::ADMIN_ELASTIC_ORPHAN);

        $menu->addChild(t('key.channels', [], 'emsco-core'), 'fa fa-eye', 'ems_core_channel_index');
        $menu->addChild(t('key.dashboards', [], 'emsco-core'), 'fa fa-dashboard', Routes::DASHBOARD_ADMIN_INDEX);
        $menu->addChild(t('key.query_searches', [], 'emsco-core'), 'fa fa-list-alt', 'ems_core_query_search_index');
        $menu->addChild(t('key.wysiwyg', [], 'emsco-core'), 'fa fa-edit', Routes::WYSIWYG_INDEX);
        $menu->addChild(t('sidebar-menu.search', [], 'emsco-core'), 'fa fa-search', 'ems_search_options_index')->setTranslation([]);
        $menu->addChild(t('key.i18n', [], 'emsco-core'), 'fa fa-language', Routes::I18N_INDEX);
        $jobMenu = $menu->addChild(t('key.jobs', [], 'emsco-core'), 'fa fa-terminal', 'job.index');
        $jobMenu->setTranslation([]);
        $jobMenu->addChild(t('sidebar-menu.create-job', [], 'emsco-core'), 'fa fa-plus', 'job.add')->setTranslation([]);
        $jobMenu->addChild(t('key.job_logs', [], 'emsco-core'), 'fa fa-file-text-o', 'job.index');
        $jobMenu->addChild(t('key.schedule', [], 'emsco-core'), 'fa fa-calendar-o', Routes::SCHEDULE_INDEX);

        $menu->addChild(t('key.analyzers', [], 'emsco-core'), 'fa fa-signal', Routes::ANALYZER_INDEX);
        $menu->addChild(t('key.filters', [], 'emsco-core'), 'fa fa-filter', Routes::FILTER_INDEX);

        $webhooks = $menu->addChild(t('key.webhooks', [], 'emsco-core'), 'fa fa-chain', Routes::WEBHOOK_SUBSCRIPTION_INDEX);
        $webhooks->addChild(t('key.webhook_subscriptions', [], 'emsco-core'), 'fa fa-solid fa-registered', Routes::WEBHOOK_SUBSCRIPTION_INDEX);

        $mcpMenu = $menu->addChild(
            label: t('key.mcp', [], 'emsco-core'),
            icon: 'fa fa-medium',
            route: Routes::MCP_TOOL_INDEX
        );
        $mcpMenu->addChild(t('key.mcp_tools', [], 'emsco-core'), 'fa fa-wrench', Routes::MCP_TOOL_INDEX);
        $mcpMenu->addChild(t('key.mcp_prompts', [], 'emsco-core'), 'fa fa-commenting-o', Routes::MCP_PROMPT_INDEX);
        $mcpMenu->addChild(t('key.mcp_resources', [], 'emsco-core'), 'fa fa-file-code-o', Routes::MCP_RESOURCE_INDEX);

        $logsMenu = $menu->addChild(t('key.logs', [], 'emsco-core'), 'fa fa-file-text', Routes::LOG_INDEX);
        $logsMenu->addChild(t('key.system_logs', [], 'emsco-core'), 'fa fa-file-text', Routes::LOG_INDEX);
        $logsMenu->addChild(t('key.uploaded_files_logs', [], 'emsco-core'), 'fa fa-upload', Routes::UPLOAD_ASSET_ADMIN_OVERVIEW);

        return $menu;
    }

    private function getCrmMenu(): Menu
    {
        $menu = new Menu(t('form_submissions.title', [], 'emsco-core'));
        if (!$this->isGranted('ROLE_FORM_CRM')) {
            return $menu;
        }
        $menu->addChild(t('form_submissions.overview', [], 'emsco-core'), 'fa fa-list-alt', 'form.submissions')->setTranslation([]);

        return $menu;
    }

    private function getPublisherMenu(): Menu
    {
        $menu = new Menu(t('sidebar-menu.publishers', [], 'emsco-core'));
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            return $menu;
        }
        $menu->addChild(t('sidebar-menu.release-admin.index-link', [], 'emsco-core'), 'fa fa-cube', 'emsco_release_index')->setTranslation([]);
        $menu->addChild(t('sidebar-menu.compare-environments', [], 'emsco-core'), 'fa fa-align-center', 'environment.align')->setTranslation([]);
        $menu->addChild(t('sidebar-menu.uploaded-files', [], 'emsco-core'), 'fa fa-upload', Routes::UPLOAD_ASSET_PUBLISHER_OVERVIEW)->setTranslation([]);

        return $menu;
    }
}
