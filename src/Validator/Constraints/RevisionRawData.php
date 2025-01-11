<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Entity\ContentType;
use Symfony\Component\Validator\Constraint;

class RevisionRawData extends Constraint
{
    public ContentType $contentType;

    public string $versionFromRequired = 'revision.raw_data.version_from_required';
    public string $versionToGreater = 'revision.raw_data.version_to_greater';
    public string $versionToGreaterOneDay = 'revision.raw_data.version_to_greater_one_day';

    #[\Override]
    public function getRequiredOptions(): array
    {
        return ['contentType'];
    }
}
