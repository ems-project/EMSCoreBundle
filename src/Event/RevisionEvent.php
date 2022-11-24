<?php

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Revision;
use Symfony\Contracts\EventDispatcher\Event;

class RevisionEvent extends Event
{
    protected Revision $revision;

    public function __construct(Revision $revision)
    {
        $this->revision = $revision;
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }
}
