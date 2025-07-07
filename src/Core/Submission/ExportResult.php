<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Submission;

final readonly class ExportResult
{
    public function __construct(
        public int $unprocessedSubmissionsCount,
        public int $exportCount
    ) {
    }
}
