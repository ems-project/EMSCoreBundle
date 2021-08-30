<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use Elastica\Document;
use EMS\CoreBundle\Entity\Revision;

final class RevisionDocument
{
    private Revision $revision;
    private Document $document;

    public function __construct(Revision $revision, Document $document)
    {
        $this->revision = $revision;
        $this->document = $document;
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }
}
