<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Entity\EntityInterface;

final readonly class EntityRow implements TableRowInterface
{
    public function __construct(private EntityInterface $entity)
    {
    }

    #[\Override]
    public function getData(): EntityInterface
    {
        return $this->entity;
    }
}
