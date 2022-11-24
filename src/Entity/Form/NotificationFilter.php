<?php

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Template;

/**
 * RebuildIndex.
 */
class NotificationFilter
{
    /**
     * @var Template
     */
    private $template;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var ContentType
     */
    private $contentType;

    /**
     * Set the template filter.
     *
     * @param Template $template
     *
     * @return NotificationFilter
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get the selected template filter.
     *
     * @return Template
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set the $environment filter.
     *
     * @param Environment $environment
     *
     * @return NotificationFilter
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Get the selected environment filter.
     *
     * @return Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Set the $contentType filter.
     *
     * @param ContentType $contentType
     *
     * @return NotificationFilter
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Get the selected contentType filter.
     *
     * @return ContentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }
}
