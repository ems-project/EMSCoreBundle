<?php
namespace EMS\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use EMS\CoreBundle\Entity\Revision;

/**
 */
class RevisionNewDraftEvent extends RevisionEvent
{
	const NAME = 'revision.new_draft';
}