<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Twig\Extension\RuntimeExtensionInterface;

class RevisionRuntime implements RuntimeExtensionInterface
{
    /** @var RevisionService */
    private $revisionService;

    public function __construct(RevisionService $revisionService)
    {
        $this->revisionService = $revisionService;
    }

    public function getRevision(string $ouuid, string $contentTypeName): ?Revision
    {
        return $this->revisionService->getCurrentRevisionByOuuidAndContentType($ouuid, $contentTypeName);
    }

    public function getRevisionId(string $ouuid, string $contentTypeName): ?int
    {
        $revision = $this->revisionService->getCurrentRevisionByOuuidAndContentType($ouuid, $contentTypeName);
        if ($revision === null) {
            return null;
        }
        return $revision->getId();
    }
}
