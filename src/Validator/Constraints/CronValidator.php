<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints;

use Cron\CronExpression;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

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
                ->buildViolation($constraint->invalid)
                ->setTranslationDomain(EMSCoreBundle::TRANS_DOMAIN_VALIDATORS)
                ->atPath('cron')
                ->addViolation();
        }
    }
}
