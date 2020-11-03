<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

class RevisionNewDraftEvent extends RevisionEvent
{
    const NAME = 'revision.new_draft';
}
