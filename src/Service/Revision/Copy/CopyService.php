<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use Elastica\Result;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Elasticsearch\Exception\NotFoundException;
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

    public function copyFromResult(CopyContext $copyContext, Result $document): Revision
    {
        $contentTypeName = $document->getSource()[EMSSource::FIELD_CONTENT_TYPE] ?? null;
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected not string content type');
        }
        $revision = $this->revisionService->getCurrentRevisionByOuuidAndContentType($contentTypeName, $document->getId());
        if (null === $revision) {
            throw new NotFoundException($document->getId());
        }

        $copiedRevision = $revision->clone();
        $copiedRevision->setRawData(\array_merge($copiedRevision->getRawData(), $copyContext->getMerge()));
        $this->finalizeRevision($copiedRevision);

        return $revision;
    }

    private function finalizeRevision(Revision $copiedRevision): void
    {
        $form = null;
        $this->dataService->finalizeDraft($copiedRevision, $form, 'copy_service');
    }
}
