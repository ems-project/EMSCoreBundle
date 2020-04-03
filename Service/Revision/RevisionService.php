<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Contracts\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;

class RevisionService
{
    /** @var RevisionRepository */
    private $revisionRepository;

    public function __construct(RevisionRepository $revisionRepository)
    {
        $this->revisionRepository = $revisionRepository;
    }

    public function getCurrentRevisionForDocument(DocumentInterface $document): ?Revision
    {
        return $this->revisionRepository->findCurrentByOuuidAndContentTypeName(
            $document->getId(),
            $document->getContentType()
        );
    }

    public function getCurrentRevisionByOuuidAndContentType(string $ouuid, string $contentType): ?Revision
    {
        return $this->revisionRepository->findCurrentByOuuidAndContentTypeName($ouuid, $contentType);
    }
}
