<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

class DuplicateOuuidException extends ElasticmsException
{
    public function __construct(string $ouuid, string $contentTypeName)
    {
        parent::__construct(
            message: \sprintf('Duplicate ouuid "%s" for content type "%s"', $ouuid, $contentTypeName)
        );
    }
}
