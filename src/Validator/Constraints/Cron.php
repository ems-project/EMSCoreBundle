<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Cron extends Constraint
{
    #[HasNamedArguments]
    public function __construct(
        public string $invalid = 'cron.invalid-format',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }
}
