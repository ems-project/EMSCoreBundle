<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

class RevisionFinalizeDraftEvent extends RevisionEvent
{
    const NAME = 'revision.finalize_draft';
}
