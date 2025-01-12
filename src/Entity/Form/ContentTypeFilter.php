<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;

/**
 * RebuildIndex.
 */
class ContentTypeFilter
{
    private ?ContentType $contentType = null;

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
        if (null === $this->contentType) {
            throw new \RuntimeException('Unexpected null contentType');
        }

        return $this->contentType;
    }
}
