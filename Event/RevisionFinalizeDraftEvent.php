<?php
namespace EMS\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use EMS\CoreBundle\Entity\Revision;

/**
 */
class RevisionFinalizeDraftEvent extends RevisionEvent
{
    const NAME = 'revision.finalize_draft';
}
