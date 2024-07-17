<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Service\QueryServiceInterface;

interface QueryServiceTypeInterface extends QueryServiceInterface
{
    public function getLoadMaxRows(): int;

    public function build(QueryTable $table): void;

    public function getQueryName(): string;
}
