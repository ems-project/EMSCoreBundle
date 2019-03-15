<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class AliasName extends Constraint
{
    public $invalid = 'Must respects the following regex {{ regex }}';
}
