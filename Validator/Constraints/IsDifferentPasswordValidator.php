<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsDifferentPasswordValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $currentPassword = $_POST['fos_user_change_password_form']['current_password'];
        $newPassword = $_POST['fos_user_change_password_form']['plainPassword']['first'];

        if ($currentPassword === $newPassword) {
            $this->context->addViolation($constraint->message);
            return false;
        }
        return true;
    }
}
