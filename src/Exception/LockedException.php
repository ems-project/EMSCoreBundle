<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class LockedException extends \Exception
{
    public function __construct(private readonly Revision $revision)
    {
        parent::__construct(\sprintf('Document %s is currently locked by %s', $revision->getLabel(), $revision->getLockBy()));
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }
}
