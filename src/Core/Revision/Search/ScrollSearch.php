<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Elastica\Scroll;
use EMS\CoreBundle\Entity\Environment;

final class ScrollSearch
{
    public Environment $environment;
    public Scroll $scroll;
    public int $total;

    public function __construct(Scroll $scroll, Environment $environment, int $total)
    {
        $this->scroll = $scroll;
        $this->environment = $environment;
        $this->total = $total;
    }
}
