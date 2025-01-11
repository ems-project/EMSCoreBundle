<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IsDifferentPassword extends Constraint
{
    public string $message = 'Password has to be different from the previous one.';

    #[\Override]
    public function getTargets(): string
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
