<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use EMS\CommonBundle\Elasticsearch\Document\DocumentCollectionInterface;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;

final class CopyService
{
    /** @var DataService */
    private $dataService;
    /** @var RevisionService */
    private $revisionService;

    public function __construct(DataService $dataService, RevisionService $revisionService)
    {
        $this->dataService = $dataService;
        $this->revisionService = $revisionService;
    }

    /**
     * @return \Generator|Revision[]
     */
    public function copyFromDocuments(DocumentCollectionInterface $documents): \Generator
    {
        foreach ($documents as $document) {
            $revision = $this->revisionService->getCurrentRevisionForDocument($document);

            if (null === $revision) {
                continue;
            }

            $copiedRevision = $revision->clone();
            $this->finalizeRevision($copiedRevision);

            yield $revision;
        }
    }

    private function finalizeRevision(Revision $copiedRevision): void
    {
        $form = null;
        $this->dataService->finalizeDraft($copiedRevision,$form , 'copy_service');
    }
}