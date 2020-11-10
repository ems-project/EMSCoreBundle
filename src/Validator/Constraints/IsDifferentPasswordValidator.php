<?php

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use EMS\CoreBundle\Validator\DTO\Credentials;

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

        try {
            $credentials = new Credentials(
                $_POST['fos_user_change_password_form']['current_password'],
                $_POST['fos_user_change_password_form']['plainPassword']['first']
            );
        } catch (\Exception $e) {
            return true;
        }

        if ($credentials->getCurrentPassword() === $credentials->getNewPassword()) {
            $this->context->addViolation($constraint->message);
            return false;
        }
        return true;
    }
}
