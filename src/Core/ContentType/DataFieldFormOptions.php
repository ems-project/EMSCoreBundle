<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

class DataFieldFormOptions
{
    public function __construct(
        public readonly ?string $locale = 'en',
        public readonly ?string $referredEmsId = null,
    ) {
    }
}
