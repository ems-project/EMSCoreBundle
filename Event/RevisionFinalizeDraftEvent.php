<?php
namespace Ems\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Ems\CoreBundle\Entity\Revision;

/**
 */
class RevisionFinalizeDraftEvent extends RevisionEvent
{
	const NAME = 'revision.finalize_draft';
}