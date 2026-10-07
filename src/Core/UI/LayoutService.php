<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function Symfony\Component\Translation\t;

class LayoutService
{
    /**
     * @var string[]
     */
    private array $activePaths = [];

    public function __construct(
        private readonly DashboardManager $dashboardManager,
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ElasticaService $elasticaService,
        private readonly AssetExtractorService $assetExtractorService,
        private readonly RouterInterface $router,
        private readonly bool $groupFeature,
    ) {
    }

    public function getStatus(): string
    {
        $status = $this->elasticaService->getHealthStatus();

        return 'green' === $status ? $this->assetExtractorService->getStatus() : $status;
    }

    public function getTopbarDashboardMenu(): Menu
    {
        $menu = new Menu(t('key.dashboards', [], 'emsco-core'));
        foreach ($this->dashboardManager->getVisibleTopbarDashboards() as $dashboard) {
            if (!$this->isGranted($dashboard->getRole())) {
                continue;
            }
            $menu->addChild(
                label: $dashboard->getLabel(),
                icon: $dashboard->getIcon(),
                route: Routes::DASHBOARD,
                routeParameters: ['name' => $dashboard->getName()],
                color: $dashboard->getColor()
            );
        }

        return $menu;
    }

    /**
     * @return Menu[]
     */
    public function getSidebarMenus(UserInterface $user): array
    {
        return \array_filter([
            $this->sidebarDashboards($user),
            $this->sidebarContentTypes($user),
            $this->sidebarPublisher(),
            $this->sidebarCrm(),
            $this->sidebarUserAdmin(),
            $this->sidebarAdmin(),
            $this->sidebarOther(),
        ]);
    }

    public function isPathActive(string $path): bool
    {
        return \in_array($path, $this->activePaths, true);
    }

    public function setCurrentPage(Page $page): void
    {
        foreach ($page->getBreadcrumb()->items ?? [] as $item) {
            if (!$item->route) {
                continue;
            }

            $this->activePaths[] = $this->router->generate($item->route, $item->routeParams);
        }
    }

    private function sidebarDashboards(UserInterface $user): ?Menu
    {
        $menu = new Menu(t('key.dashboards', [], 'emsco-core'));

        foreach ($this->dashboardManager->getVisibleSidebarDashboards() as $dashboard) {
            if (!$this->isGranted($dashboard->getRole())) {
                continue;
            }

            $menu->addChild(
                label: $dashboard->getLabel($user),
                icon: $dashboard->getIcon(),
                route: Routes::DASHBOARD,
                routeParameters: ['name' => $dashboard->getName()],
                color: $dashboard->getColor()
            );
        }

        return $menu->hasChildren() ? $menu : null;
    }

    private function sidebarContentTypes(UserInterface $user): ?Menu
    {
        $counters = $this->contentTypeService->getDraftCounters($user);
        $menu = new Menu(t('key.content_types', [], 'emsco-core'));

        foreach ($this->contentTypeService->getActiveContentTypes() as $contentType) {
            $roles = $contentType->getRoles();

            if (!$contentType->getRootContentType() && !$this->isGranted($roles[ContentTypeRoles::VIEW])) {
                continue;
            }

            [$route, $routeParameters] = $this->contentTypeService->getRedirectOverviewRoute($contentType);
            $entry = new MenuEntry(
                label: $contentType->getPluralName($user),
                icon: $contentType->getIcon() ?? 'fa fa-book',
                route: $route,
                routeParameters: $routeParameters,
                color: $contentType->getColor()
            );

            $counter = $counters[$contentType->getId()] ?? null;
            if (null !== $counter) {
                $entry->setBadge((string) $counter);
            }

            foreach ($contentType->getViews() as $view) {
                if ('ems.view.data_link' === $view->getType() || !$this->isGranted($view->getRole())) {
                    continue;
                }

                $entry->addChild(
                    label: $view->getLabel($user),
                    icon: $view->getIcon() ?? '',
                    route: $view->isPublic() ? Routes::DATA_PUBLIC_VIEW : Routes::DATA_PRIVATE_VIEW,
                    routeParameters: ['viewId' => $view->getId()]
                );
            }

            if ($entry->hasBadge()
                && $contentType->giveEnvironment()->getManaged()
                && $this->isGranted($contentType->role(ContentTypeRoles::EDIT))) {
                $entry
                    ->addChild(
                        label: t('key.draft_in_progress', [], 'emsco-core'),
                        icon: 'fa fa-fire',
                        route: Routes::DRAFT_IN_PROGRESS,
                        routeParameters: ['contentTypeId' => $contentType->getId()]
                    )
                    ->setBadge($entry->getBadge(), $contentType->getColor());
            }

            if ($this->isGranted($roles[ContentTypeRoles::SHOW_LINK_CREATE]) && $this->isGranted($roles[ContentTypeRoles::CREATE])) {
                $entry->addChild(
                    label: t('action.new_entity_name', $contentType->getSingularNameTranslation($user)->getParameters(), 'emsco-core'),
                    icon: 'fa fa-plus',
                    route: Routes::DATA_ADD,
                    routeParameters: ['contentType' => $contentType->getId()]
                );
            }

            if ($this->isGranted($roles[ContentTypeRoles::TRASH])) {
                $entry->addChild(
                    label: t('key.trash', [], 'emsco-core'),
                    icon: 'fa fa-trash',
                    route: Routes::DATA_TRASH,
                    routeParameters: ['contentType' => $contentType->getId()]
                );
            }

            if ($entry->hasChildren()) {
                $menu->addMenuEntry($entry);
            }
        }

        return $menu->hasChildren() ? $menu : null;
    }

    private function sidebarPublisher(): ?Menu
    {
        if (!$this->isGranted(Roles::ROLE_PUBLISHER)) {
            return null;
        }

        $menu = new Menu(t('key.publishers', [], 'emsco-core'));
        $menu->addChild(t('key.releases', [], 'emsco-core'), 'fa fa-cube', 'emsco_release_index');
        $menu->addChild(t('key.compare_environments', [], 'emsco-core'), 'fa fa-align-center', 'environment.align');
        $menu->addChild(t('key.uploaded_files', [], 'emsco-core'), 'fa fa-upload', Routes::UPLOAD_ASSET_PUBLISHER_OVERVIEW);

        return $menu;
    }

    private function sidebarCrm(): ?Menu
    {
        if (!$this->isGranted(Roles::ROLE_FORM_CRM)) {
            return null;
        }

        $menu = new Menu(t('key.form_submissions', [], 'emsco-core'));
        $menu->addChild(t('key.overview', [], 'emsco-core'), 'fa fa-list-alt', 'form.submissions');

        return $menu;
    }

    private function sidebarUserAdmin(): ?Menu
    {
        if (!$this->isGranted(Roles::ROLE_USER_MANAGEMENT)) {
            return null;
        }

        $menu = new Menu(t('key.user_management', [], 'emsco-core'));
        $menu->addChild(t('key.users', [], 'emsco-core'), 'fa fa-users', Routes::USER_INDEX);
        if ($this->groupFeature) {
            $menu->addChild(t('key.groups', [], 'emsco-core'), 'fa fa-list-ul', Routes::GROUP_INDEX);
        }

        return $menu;
    }

    private function sidebarAdmin(): ?Menu
    {
        if (!$this->isGranted(Roles::ROLE_ADMIN)) {
            return null;
        }

        $menu = new Menu(t('key.admin', [], 'emsco-core'));

        $contentMenu = $menu->addChild(t('key.content', [], 'emsco-core'), 'fa fa-pencil');
        $contentMenu->addChild(t('key.content_types', [], 'emsco-core'), 'fa fa-sitemap', Routes::ADMIN_CONTENT_TYPE_INDEX);
        $contentMenu->addChild(t('key.dashboards', [], 'emsco-core'), 'fa fa-dashboard', Routes::DASHBOARD_ADMIN_INDEX);
        $contentMenu->addChild(t('key.forms', [], 'emsco-core'), 'fa fa-keyboard-o', Routes::ADMIN_FORM_INDEX);
        $contentMenu->addChild(t('key.query_searches', [], 'emsco-core'), 'fa fa-search', 'ems_core_query_search_index');
        $contentMenu->addChild(t('key.wysiwyg', [], 'emsco-core'), 'fa fa-edit', Routes::WYSIWYG_INDEX);
        $contentMenu->addChild(t('key.i18n', [], 'emsco-core'), 'fa fa-language', Routes::I18N_INDEX);

        $environmentMenu = $menu->addChild(t('field.environments', [], 'emsco-core'), 'fa fa-database');
        $environmentMenu->addChild(t('key.overview', [], 'emsco-core'), 'fa fa-list-ul', Routes::ADMIN_ENVIRONMENT_INDEX);
        $environmentMenu->addChild(t('key.channels', [], 'emsco-core'), 'fa fa-eye', 'ems_core_channel_index');
        $environmentMenu->addChild(t('key.unreferenced_aliases', [], 'emsco-core'), 'fa fa-chain', Routes::ADMIN_ELASTIC_UNREFERENCED_ALIASES);
        $environmentMenu->addChild(t('key.orphan_indexes', [], 'emsco-core'), 'fa fa-chain-broken', Routes::ADMIN_ELASTIC_ORPHAN);

        $jobMenu = $menu->addChild(t('key.jobs', [], 'emsco-core'), 'fa fa-terminal');
        $jobMenu->addChild(t('action.new_job', [], 'emsco-core'), 'fa fa-plus', 'job.add');
        $jobMenu->addChild(t('key.job_logs', [], 'emsco-core'), 'fa fa-file-text-o', 'job.index');
        $jobMenu->addChild(t('key.schedule', [], 'emsco-core'), 'fa fa-calendar-o', Routes::SCHEDULE_INDEX);

        $clusterMenu = $menu->addChild(t('key.cluster', [], 'emsco-core'), 'fa fa-cubes');
        $clusterMenu->addChild(t('key.analyzers', [], 'emsco-core'), 'fa fa-signal', Routes::ANALYZER_INDEX);
        $clusterMenu->addChild(t('key.filters', [], 'emsco-core'), 'fa fa-filter', Routes::FILTER_INDEX);

        $webhooks = $menu->addChild(t('key.webhooks', [], 'emsco-core'), 'fa fa-chain');
        $webhooks->addChild(t('key.webhook_subscriptions', [], 'emsco-core'), 'fa fa-solid fa-registered', Routes::WEBHOOK_SUBSCRIPTION_INDEX);

        $mcpMenu = $menu->addChild(t('key.mcp', [], 'emsco-core'), 'fa fa-plug');
        $mcpMenu->addChild(t('key.mcp_tools', [], 'emsco-core'), 'fa fa-wrench', Routes::MCP_TOOL_INDEX);
        $mcpMenu->addChild(t('key.mcp_prompts', [], 'emsco-core'), 'fa fa-terminal', Routes::MCP_PROMPT_INDEX);
        $mcpMenu->addChild(t('key.mcp_resources', [], 'emsco-core'), 'fa fa-file', Routes::MCP_RESOURCE_INDEX);

        $logsMenu = $menu->addChild(t('key.logs', [], 'emsco-core'), 'fa fa-file-text');
        $logsMenu->addChild(t('key.system_logs', [], 'emsco-core'), 'fa fa-file-text', Routes::LOG_INDEX);
        $logsMenu->addChild(t('key.uploaded_files_logs', [], 'emsco-core'), 'fa fa-upload', Routes::UPLOAD_ASSET_ADMIN_OVERVIEW);

        return $menu;
    }

    private function sidebarOther(): Menu
    {
        $menu = new Menu(t('key.other', [], 'emsco-core'));
        $menu->addChild(
            label: t('key.documentation', [], 'emsco-core'),
            icon: 'fa fa-book',
            route: 'documentation'
        );

        return $menu;
    }

    private function isGranted(?string $role): bool
    {
        return null === $role || $this->authorizationChecker->isGranted($role);
    }
}
