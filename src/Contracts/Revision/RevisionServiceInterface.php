<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Contracts\Revision;

use EMS\CoreBundle\Core\Revision\Revisions;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;

interface RevisionServiceInterface
{
    /**
     * @param array{contentType?: ContentType, contentTypeName?: string, lockBy?: string, archived?: bool, endTime?: (string|null), modifiedBefore?: string} $search
     */
    public function search(array $search): Revisions;

    /**
     * @param array<mixed> $rawData
     */
    public function updateRawData(Revision $revision, array $rawData, string $username, bool $merge = true): Revision;
}
