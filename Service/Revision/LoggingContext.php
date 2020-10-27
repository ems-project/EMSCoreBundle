<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Revision;

final class LoggingContext
{
    /**
     * @return array<string, int|string|null>
     */
    public static function read(Revision $revision): array
    {
        $context = self::context($revision);
        $context[EmsFields::LOG_OPERATION_FIELD] = EmsFields::LOG_OPERATION_READ;

        return $context;
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function update(Revision $revision): array
    {
        $context = self::context($revision);
        $context[EmsFields::LOG_OPERATION_FIELD] = EmsFields::LOG_OPERATION_UPDATE;

        return $context;
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function context(Revision $revision): array
    {
        return [
            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
        ];
    }
}