<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Service\EntityServiceInterface;

abstract class AbstractEntityTableType extends AbstractTableType
{
    final public const LOAD_MAX_ROWS = 400;

    public function __construct(
        private readonly EntityServiceInterface $entityService
    ) {
    }

    public function build(EntityTable $table): void
    {
    }

    public function getEntityService(): EntityServiceInterface
    {
        return $this->entityService;
    }

    public function getLoadMaxRows(): int
    {
        return self::LOAD_MAX_ROWS;
    }
}
