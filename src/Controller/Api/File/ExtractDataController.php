<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\File;

use EMS\CoreBundle\Service\AssetExtractorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExtractDataController
{
    public function __construct(private readonly AssetExtractorService $assetExtractorService)
    {
    }

    public function get(string $hash): Response
    {
        return new JsonResponse($this->assetExtractorService->extractData($hash));
    }
}
