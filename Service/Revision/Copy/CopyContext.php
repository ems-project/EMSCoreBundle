<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

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

    public function getBody(): array
    {
        return $this->searchQuery;
    }

    public function getIndex(): string
    {
        return $this->environment->getAlias();
    }

    public function getMerge(): array
    {
        return $this->merge;
    }

    public function setMerge(array $merge): void
    {
        $this->merge = $merge;
    }
}
