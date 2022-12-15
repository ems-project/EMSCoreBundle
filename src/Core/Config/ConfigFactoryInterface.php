<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Config;

interface ConfigFactoryInterface
{
    /** @param array<mixed> $options */
    public function createFromOptions(array $options): ConfigInterface;

    public function createFromHash(string $hash): ConfigInterface;
}
