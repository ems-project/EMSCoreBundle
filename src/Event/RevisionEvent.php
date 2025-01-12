<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Revision;
use Symfony\Contracts\EventDispatcher\Event;

class RevisionEvent extends Event
{
    public function __construct(protected Revision $revision)
    {
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }
}
