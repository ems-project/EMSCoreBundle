<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Copy;

use EMS\CommonBundle\Search\Search;

final class CopyContext
{
    /** @var array<mixed> */
    private $merge;
    /** @var Search */
    private $search;

    public function __construct(Search $searchQuery)
    {
        $this->search = $searchQuery;
        $this->merge = [];
    }

    public function getMerge(): array
    {
        return $this->merge;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function setMerge(array $merge): void
    {
        $this->merge = $merge;
    }
}
