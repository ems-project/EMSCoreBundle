<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Service\EntityServiceInterface;

abstract class AbstractEntityTableType extends AbstractTableType
{
    public function __construct(
        private readonly EntityServiceInterface $entityService,
    ) {
    }

    public function build(EntityTable $table): void
    {
    }

    public function getEntityService(): EntityServiceInterface
    {
        return $this->entityService;
    }
}
