<?php

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

class RevisionPublishEvent extends RevisionEvent
{
    public const NAME = 'revision.publish';

    protected $environment;

    public function __construct(Revision $revision, Environment $environment)
    {
        parent::__construct($revision);
        $this->environment = $environment;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }
}
