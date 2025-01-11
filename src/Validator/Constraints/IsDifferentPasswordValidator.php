<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsDifferentPasswordValidator extends ConstraintValidator
{
    /**
     * @param mixed                          $value
     * @param Constraint|IsDifferentPassword $constraint
     */
    #[\Override]
    public function validate($value, $constraint): bool
    {
        if (!$constraint instanceof IsDifferentPassword) {
            return false;
        }

        if (!isset($_POST['emsco_change_password'])) {
            return true;
        }

        $old = $_POST['emsco_change_password']['current_password'];
        $new = $_POST['emsco_change_password']['plainPassword']['first'];
        if ($old === $new) {
            $this->context->addViolation($constraint->message);

            return false;
        }

        return true;
    }
}
