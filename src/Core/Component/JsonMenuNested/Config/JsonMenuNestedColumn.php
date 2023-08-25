<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

class JsonMenuNestedColumn
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $width = null,
    ) {
    }
}
