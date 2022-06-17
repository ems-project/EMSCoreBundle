<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Log;

use EMS\CoreBundle\Entity\Revision;

final class LogEntityTableContext
{
    /** @var string[] */
    public array $channels = [];
    public ?Revision $revision = null;

    public int $from = 0;
    public int $size = 0;
    public ?string $orderField = null;
    public string $orderDirection = 'ASC';
    public string $searchValue = '';
}
