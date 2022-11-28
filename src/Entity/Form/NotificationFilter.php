<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Template;

class NotificationFilter
{
    private ?Template $template = null;
    private ?Environment $environment = null;

    private ?ContentType $contentType = null;

    public function setTemplate(?Template $template): NotificationFilter
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setEnvironment(?Environment $environment): NotificationFilter
    {
        $this->environment = $environment;

        return $this;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setContentType(?ContentType $contentType): NotificationFilter
    {
        $this->contentType = $contentType;

        return $this;
    }

    public function getContentType(): ?ContentType
    {
        return $this->contentType;
    }
}
