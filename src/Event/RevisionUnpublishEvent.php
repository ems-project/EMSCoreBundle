<?php

namespace EMS\CoreBundle\Event;

class RevisionUnpublishEvent extends RevisionPublishEvent
{
    public const NAME = 'revision.unpublish';
}
