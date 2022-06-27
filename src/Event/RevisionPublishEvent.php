<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

class RevisionPublishEvent extends RevisionEvent
{
    protected Environment $environment;

    public function __construct(Revision $revision, Environment $environment)
    {
        parent::__construct($revision);
        $this->environment = $environment;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
