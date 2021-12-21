<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Entity\EntityInterface;

final class EntityRow implements TableRowInterface
{
    private EntityInterface $entity;

    public function __construct(EntityInterface $entity)
    {
        $this->entity = $entity;
    }

    public function getData(): EntityInterface
    {
        return $this->entity;
    }
}
