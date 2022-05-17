<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Copy;

use EMS\CommonBundle\Search\Search;

final class CopyContext
{
    /** @var array<mixed> */
    private array $merge;
    private Search $search;

    public function __construct(Search $searchQuery)
    {
        $this->search = $searchQuery;
        $this->merge = [];
    }

    /**
     * @return array<mixed>
     */
    public function getMerge(): array
    {
        return $this->merge;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    /**
     * @param array<mixed> $merge
     */
    public function setMerge(array $merge): void
    {
        $this->merge = $merge;
    }
}
