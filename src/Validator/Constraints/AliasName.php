<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AliasName extends Constraint
{
    public string $invalid = 'Must respects the following regex {{ regex }}';
}
