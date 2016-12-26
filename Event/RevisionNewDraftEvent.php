<?php
namespace Ems\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Ems\CoreBundle\Entity\Revision;

/**
 */
class RevisionNewDraftEvent extends RevisionEvent
{
	const NAME = 'revision.new_draft';
}