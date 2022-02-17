<?php

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class LockedException extends \Exception
{
    private Revision $revision;

    public function __construct(Revision $revision)
    {
        $this->revision = $revision;
        parent::__construct(\sprintf('Document %s is currently locked by %s', $revision->getLabel(), $revision->getLockBy()));
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }
}
