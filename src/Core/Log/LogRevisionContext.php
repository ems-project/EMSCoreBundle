<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Log;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

final class LogRevisionContext
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
    public static function delete(Revision $revision): array
    {
        $context = self::context($revision);
        $context[EmsFields::LOG_OPERATION_FIELD] = EmsFields::LOG_OPERATION_DELETE;

        return $context;
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function publish(Revision $revision, Environment $environment): array
    {
        $context = self::context($revision);
        $context[EmsFields::LOG_ENVIRONMENT_FIELD] = $environment->getName();

        return $context;
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function unpublish(Revision $revision, Environment $environment): array
    {
        $context = self::context($revision);
        $context[EmsFields::LOG_OPERATION_FIELD] = EmsFields::LOG_OPERATION_DELETE;
        $context[EmsFields::LOG_ENVIRONMENT_FIELD] = $environment->getName();

        return $context;
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function context(Revision $revision): array
    {
        $context = [
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            'label' => $revision->getLabel(),
        ];

        if ($contentType = $revision->getContentType()) {
            $context[EmsFields::LOG_CONTENTTYPE_FIELD] = $contentType->getName();

            if ($contentTypeEnvironment = $contentType->getEnvironment()) {
                $context[EmsFields::LOG_ENVIRONMENT_FIELD] = $contentTypeEnvironment->getName();
            }
        }

        return $context;
    }
}
