<?php

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

class TwigElementsController extends AbstractController
{
    final public const ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';

    public function __construct(private readonly AssetExtractorService $assetExtractorService, private readonly ElasticaService $elasticaService, private readonly UserService $userService, private readonly JobService $jobService, private readonly DashboardManager $dashboardManager, private readonly ContentTypeService $contentTypeService)
    {
    }

    public function sideMenuAction(): Response
    {
        $draftCounterGroupedByContentType = [];

        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $this->getDoctrine()->getRepository(Revision::class);
        $user = $this->userService->getCurrentUser();

        $temp = $revisionRepository->draftCounterGroupedByContentType($user->getCircles(), $this->isGranted('ROLE_USER_MANAGEMENT'));
        foreach ($temp as $item) {
            $draftCounterGroupedByContentType[$item['content_type_id']] = $item['counter'];
        }

        $status = $this->elasticaService->getHealthStatus();

        if ('green' === $status) {
            $status = $this->getAssetExtractorStatus($this->assetExtractorService);
        }

        return $this->render(
            '@EMSCore/elements/side-menu.html.twig',
            [
                'draftCounterGroupedByContentType' => $draftCounterGroupedByContentType,
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

    public function jobsAction(string $username): Response
    {
        return $this->render(
            '@EMSCore/elements/jobs-list.html.twig',
            [
                'jobs' => $this->jobService->findByUser($username),
            ]
        );
    }

    private function getAssetExtractorStatus(AssetExtractorService $assetExtractorService): string
    {
        try {
            $cache = new FilesystemAdapter('', 60);
            $cachedStatus = $cache->getItem(TwigElementsController::ASSET_EXTRACTOR_STATUS_CACHE_ID);
            if (!$cachedStatus->isHit()) {
                $cachedStatus->set($assetExtractorService->hello());
                $cache->save($cachedStatus);
            }
            $result = $cachedStatus->get();

            if (($result['code'] ?? 500) === 200) {
                return 'green';
            }
        } catch (\Exception) {
        }

        return 'yellow';
    }

    private function getOtherMenu(): Menu
    {
        $menu = new Menu('views.elements.side-menu-html.other');
        $menu->addChild('views.elements.side-menu-html.documentation', 'fa fa-book', 'documentation')->setTranslation([]);

        return $menu;
    }

    private function getUserAdminMenu(): Menu
    {
        $menu = new Menu('views.elements.side-menu-html.user-management');
        if (!$this->isGranted('ROLE_USER_MANAGEMENT')) {
            return $menu;
        }
        $menu->addChild('views.elements.side-menu-html.users', 'fa fa-users', Routes::USER_INDEX)->setTranslation([]);

        return $menu;
    }

    private function getAdminMenu(): Menu
    {
        $menu = new Menu('views.elements.side-menu-html.admin');
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $menu;
        }
        $menu->addChild('views.elements.side-menu-html.content-types', 'fa fa-sitemap', 'contenttype.index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.forms', 'fa fa-keyboard-o', Routes::FORM_ADMIN_INDEX)->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.environments', 'fa fa-database', 'environment.index')->setTranslation([]);
        $menu->addChild('view.elements.side-menu.chanel-admin.index-link', 'fa fa-eye', 'ems_core_channel_index')->setTranslation([]);
        $menu->addChild('view.elements.side-menu.dashboard-admin.index-link', 'fa fa-dashboard', Routes::DASHBOARD_ADMIN_INDEX)->setTranslation([]);
        $menu->addChild('view.elements.side-menu.query_search.index-link', 'fa fa-list-alt', 'ems_core_query_search_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.wysiwyg', 'fa fa-edit', 'ems_wysiwyg_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.search', 'fa fa-search', 'ems_search_options_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.i18n', 'fa fa-language', 'i18n_index')->setTranslation([]);
        $jobMenu = $menu->addChild('views.elements.side-menu-html.jobs', 'fa fa-terminal', 'job.index');
        $jobMenu->setTranslation([]);
        $jobMenu->addChild('views.elements.side-menu-html.create-job', 'fa fa-plus', 'job.add')->setTranslation([]);
        $jobMenu->addChild('views.elements.side-menu-html.logs', 'fa fa-file-text-o', 'job.index')->setTranslation([]);
        $jobMenu->addChild('views.elements.side-menu-html.schedule', 'fa fa-calendar-o', Routes::SCHEDULE_INDEX)->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.analyzers', 'fa fa-signal', 'ems_analyzer_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.filters', 'fa fa-filter', 'ems_filter_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.audit-logs', 'fa fa-file-text', Routes::LOG_INDEX)->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.uploaded-files-logs', 'fa fa-upload', 'ems_core_uploaded_file_logs')->setTranslation([]);

        return $menu;
    }

    private function getCrmMenu(): Menu
    {
        $menu = new Menu('form_submissions.title');
        if (!$this->isGranted('ROLE_FORM_CRM')) {
            return $menu;
        }
        $menu->addChild('form_submissions.overview', 'fa fa-list-alt', 'form.submissions')->setTranslation([]);

        return $menu;
    }

    private function getPublisherMenu(): Menu
    {
        $menu = new Menu('views.elements.side-menu-html.publishers');
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            return $menu;
        }
        $menu->addChild('view.elements.side-menu.release-admin.index-link', 'fa fa-cube', 'emsco_release_index')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.compare-environments', 'fa fa-align-center', 'environment.align')->setTranslation([]);
        $menu->addChild('views.elements.side-menu-html.uploaded-files', 'fa fa-upload', 'ems_core_uploaded_file_index')->setTranslation([]);

        return $menu;
    }
}
