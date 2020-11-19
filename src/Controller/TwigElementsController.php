<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

class TwigElementsController extends AbstractController
{
    const ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';

    public function sideMenuAction(AssetExtractorService $assetExtractorService, ElasticaService $elasticaService, UserService $userService): Response
    {
        $draftCounterGroupedByContentType = [];

        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $this->getDoctrine()->getRepository('EMSCoreBundle:Revision');
        $user = $userService->getCurrentUser();

        $temp = $revisionRepository->draftCounterGroupedByContentType($user->getCircles(), $this->isGranted('ROLE_USER_MANAGEMENT'));
        foreach ($temp as $item) {
            $draftCounterGroupedByContentType[$item['content_type_id']] = $item['counter'];
        }

        $status = $elasticaService->getHealthStatus();

        if ('green' === $status) {
            $status = $this->getAssetExtractorStatus($assetExtractorService);
        }

        return $this->render(
            '@EMSCore/elements/side-menu.html.twig',
            [
                'draftCounterGroupedByContentType' => $draftCounterGroupedByContentType,
                'status' => $status,
            ]
        );
    }

    public function jobsAction(string $username, JobService $jobService): Response
    {
        return $this->render(
            '@EMSCore/elements/jobs-list.html.twig',
            [
                'jobs' => $jobService->findByUser($username),
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
