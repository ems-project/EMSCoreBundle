<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use EMS\CommonBundle\Common\Composer\ComposerInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InfoController
{
    public function __construct(private readonly ComposerInfo $composerInfo)
    {
    }

    public function versions(): Response
    {
        return new JsonResponse($this->composerInfo->getVersionPackages());
    }
}
