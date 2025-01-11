<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class AliasNameValidator extends ConstraintValidator
{
    /**
     * @param string    $value
     * @param AliasName $constraint
     */
    #[\Override]
    public function validate($value, Constraint $constraint): void
    {
        $regex = '/^[a-z][a-z0-9\-_]*$/';

        if (!\preg_match($regex, $value) || \strlen($value) > 100) {
            $this->context
                ->buildViolation($constraint->invalid)
                ->setParameter('{{ regex }}', $regex)
                ->atPath('name')
                ->addViolation()
            ;
        }
    }
}
