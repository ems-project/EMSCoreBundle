<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use EMS\CoreBundle\Entity\ContentType;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class RevisionRawData extends Constraint
{
    #[HasNamedArguments]
    public function __construct(
        public ContentType $contentType,
        public string $versionFromRequired = 'revision.raw_data.version_from_required',
        public string $versionToGreater = 'revision.raw_data.version_to_greater',
        public string $versionToGreaterOneDay = 'revision.raw_data.version_to_greater_one_day',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    #[\Override]
    public function getRequiredOptions(): array
    {
        return ['contentType'];
    }
}
