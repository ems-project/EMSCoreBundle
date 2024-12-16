<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Core\Config\ConfigInterface;
use EMS\CoreBundle\Entity\Revision;

class JsonMenuNestedConfig implements ConfigInterface
{
    public ?string $template = null;
    public ?string $locale = null;
    /** @var array<string, mixed> */
    public array $context = [];
    public ?string $contextBlock = null;
    /** @var JsonMenuNestedColumn[] */
    public array $columns = [];
    public ?string $activeItemId;

    public function __construct(
        private readonly string $hash,
        private readonly string $id,
        public readonly Revision $revision,
        public readonly JsonMenuNested $jsonMenuNested,
        public readonly JsonMenuNestedNodes $nodes,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }
}
