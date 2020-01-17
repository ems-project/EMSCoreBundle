<?php

namespace EMS\CoreBundle\Controller;

use Elasticsearch\Client;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\JobService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class TwigElementsController extends AppController
{
    const ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';

    public function sideMenuAction(AssetExtractorService $assetExtractorService, Client $client)
    {
        $draftCounterGroupedByContentType = [];

        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $this->getDoctrine()->getRepository('EMSCoreBundle:Revision');
         
        $temp = $revisionRepository->draftCounterGroupedByContentType($this->get('ems.service.user')->getCurrentUser()->getCircles(), $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'));
        foreach ($temp as $item) {
            $draftCounterGroupedByContentType[$item["content_type_id"]] = $item["counter"];
        }

        try {
            $status = $client->cluster()->health()['status'];
        } catch (\Exception $e) {
            $status = 'red';
        }


        if ($status === 'green') {
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

    public function jobsAction(string $username, JobService $jobService)
    {
        return $this->render(
            '@EMSCore/elements/jobs-list.html.twig',
            [
                'jobs' => $jobService->findByUser($username),
            ]
        );
    }

    private function getAssetExtractorStatus(AssetExtractorService $assetExtractorService)
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
