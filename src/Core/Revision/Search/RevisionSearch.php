<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Elastica\Scroll;

final class RevisionSearch
{
    private Scroll $scroll;
    private int $total;

    public function __construct(Scroll $scroll, int $total)
    {
        $this->scroll = $scroll;
        $this->total = $total;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getScroll(): Scroll
    {
        return $this->scroll;
    }
}
