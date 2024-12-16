<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Environment;

use EMS\Helpers\Standard\DateTime;

class Index
{
    public function __construct(
        public readonly string $name,
        public readonly int $count,
    ) {
    }

    public function getBuildDate(): ?\DateTimeInterface
    {
        try {
            $endDate = \implode('_', \array_slice(\explode('_', $this->name), -2));

            return DateTime::createFromFormat($endDate, 'Ymd_His');
        } catch (\Throwable) {
            return null;
        }
    }
}
