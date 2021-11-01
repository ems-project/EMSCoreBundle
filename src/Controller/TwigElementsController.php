<?php

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

class TwigElementsController extends AbstractController
{
    public const ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';
    private ElasticaService $elasticaService;
    private AssetExtractorService $assetExtractorService;
    private UserService $userService;
    private JobService $jobService;
    private DashboardManager $dashboardManager;

    public function __construct(AssetExtractorService $assetExtractorService, ElasticaService $elasticaService, UserService $userService, JobService $jobService, DashboardManager $dashboardManager)
    {
        $this->assetExtractorService = $assetExtractorService;
        $this->elasticaService = $elasticaService;
        $this->userService = $userService;
        $this->jobService = $jobService;
        $this->dashboardManager = $dashboardManager;
    }

    public function sideMenuAction(): Response
    {
        $draftCounterGroupedByContentType = [];

        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $this->getDoctrine()->getRepository('EMSCoreBundle:Revision');
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
                'dashboardMenu' => $this->dashboardManager->getSideMenu(),
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
        } catch (\Exception $e) {
        }

        return 'yellow';
    }
}
