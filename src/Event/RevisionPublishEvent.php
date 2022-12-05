<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

class RevisionPublishEvent extends RevisionEvent
{
    public function __construct(Revision $revision, protected Environment $environment)
    {
        parent::__construct($revision);
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
