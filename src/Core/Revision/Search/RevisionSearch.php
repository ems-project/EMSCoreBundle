<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Elastica\Scroll;

final readonly class RevisionSearch
{
    public function __construct(private Scroll $scroll, private int $total)
    {
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
