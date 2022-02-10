<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

interface EntityInterface extends \EMS\CommonBundle\Entity\EntityInterface
{
    public function getName(): string;
}
