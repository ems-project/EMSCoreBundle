<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsDifferentPasswordValidator extends ConstraintValidator
{
    const ERROR_MESSAGE = 'Password has to be different from the previous one';

    public function validate($value, Constraint $constraint): bool
    {
        $currentPassword = $_POST['fos_user_change_password_form']['current_password'];
        $newPassword = $_POST['fos_user_change_password_form']['plainPassword']['first'];

        if ($currentPassword === $newPassword) {
            $this->context->addViolation(self::ERROR_MESSAGE);
            return false;
        }
        return true;
    }
}
