<?php

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;

/**
 * RebuildIndex.
 */
class ContentTypeFilter
{
    /**
     * @var ContentType
     */
    private $contentType;

    /**
     * Set the $contentType filter.
     *
     * @param ContentType $contentType
     *
     * @return ContentTypeFilter
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
