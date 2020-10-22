<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class IsDifferentPassword extends Constraint
{
    public $message = 'Password has to be different from the previous one';

    public function getTargets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
