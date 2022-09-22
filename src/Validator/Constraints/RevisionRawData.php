<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Entity\ContentType;
use Symfony\Component\Validator\Constraint;

class RevisionRawData extends Constraint
{
    public ContentType $contentType;

    public string $versionFromRequired = 'revision.raw_data.version_from_required';
    public string $versionToInvalid = 'revision.raw_data.version_to_invalid';

    public function getRequiredOptions(): array
    {
        return ['contentType'];
    }
}
