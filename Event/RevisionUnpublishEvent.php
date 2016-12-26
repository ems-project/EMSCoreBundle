<?php
namespace Ems\CoreBundle\Event;

use Ems\CoreBundle\Entity\Revision;
use Symfony\Component\EventDispatcher\Event;

/**
 */
class RevisionUnpublishEvent extends RevisionPublishEvent
{
	const NAME = 'revision.unpublish';
}