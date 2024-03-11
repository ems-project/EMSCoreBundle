<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Validator\Constraints\MediaLibrary;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DocumentDTO extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
