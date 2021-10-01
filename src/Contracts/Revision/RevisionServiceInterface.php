<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Contracts\Revision;

use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Entity\Revision;

interface RevisionServiceInterface
{
    /**
     * @param array{
     *      "contentType"?: \EMS\CoreBundle\Entity\ContentType,
     *      "contentTypeName"?: string,
     *      "lockBy"?: string,
     *      "archived"?: bool,
     *      "endTime"?: null|string,
     *      "modifiedBefore"?: string
     * } $search
     */
    public function search(array $search): Revisions;

    /**
     * @param array<mixed> $rawData
     */
    public function updateRawData(Revision $revision, array $rawData, string $username, bool $merge = true): Revision;
}
