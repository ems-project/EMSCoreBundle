<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use EMS\CommonBundle\Elasticsearch\Request\Request;
use EMS\CommonBundle\Elasticsearch\Request\RequestInterface;
use EMS\CoreBundle\Entity\Environment;

final class CopyContext
{
    /** @var Environment */
    private $environment;
    /** @var array */
    private $merge;
    /** @var array */
    private $searchQuery;

    public function __construct(Environment $environment, array $searchQuery)
    {
        $this->environment = $environment;
        $this->searchQuery = $searchQuery;
        $this->merge = [];
    }

    public function getMerge(): array
    {
        return $this->merge;
    }

    public function makeRequest(): RequestInterface
    {
        return new Request($this->environment->getAlias(), $this->searchQuery);
    }

    public function setMerge(array $merge): void
    {
        $this->merge = $merge;
    }
}
