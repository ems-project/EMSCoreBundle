<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class IsDifferentPassword extends Constraint
{
    #[HasNamedArguments]
    public function __construct(
        public string $message = 'Password has to be different from the previous one.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    #[\Override]
    public function getTargets(): string
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
