<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Service\RevisionService;
use Twig\Extension\RuntimeExtensionInterface;

class RevisionRuntime implements RuntimeExtensionInterface
{
    /** @var RevisionService */
    private $revisionService;

    public function __construct(RevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    public function getRevisionId($ouuid, $env, $contentType)
    {
        return $this->revisionService->getIdByOuuidAndContentTypeAndEnvironment($ouuid, $contentType, $env)['id'] ?? null;
    }
}
