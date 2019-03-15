<?php
namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Revision;
use Symfony\Component\EventDispatcher\Event;

/**
 */
class RevisionUnpublishEvent extends RevisionPublishEvent
{
    const NAME = 'revision.unpublish';
}
