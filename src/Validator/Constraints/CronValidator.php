<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Cron\CronExpression;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use function Symfony\Component\Translation\t;

class CronValidator extends ConstraintValidator
{
    /**
     * @param string $value
     * @param Cron   $constraint
     */
    #[\Override]
    public function validate($value, Constraint $constraint): void
    {
        if (!CronExpression::isValidExpression($value)) {
            $this->context
                ->buildViolation(t('invalid_format', [], 'validators'))
                ->atPath('cron')
                ->addViolation();
        }
    }
}
