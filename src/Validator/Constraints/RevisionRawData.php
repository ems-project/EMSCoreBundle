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
