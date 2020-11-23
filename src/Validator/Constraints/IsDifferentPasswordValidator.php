<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsDifferentPasswordValidator extends ConstraintValidator
{
    /**
     * @param mixed                          $value
     * @param Constraint|IsDifferentPassword $constraint
     */
    public function validate($value, $constraint): bool
    {
        if (! $constraint instanceof IsDifferentPassword) {
            return false;
        }

        if (!isset($_POST['fos_user_change_password_form'])) {
            return true;
        }

        $old = $_POST['fos_user_change_password_form']['current_password'];
        $new = $_POST['fos_user_change_password_form']['plainPassword']['first'];
        if ($old === $new) {
            $this->context->addViolation($constraint->message);
            return false;
        }

        return true;
    }
}
