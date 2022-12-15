<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Config;

interface ConfigInterface
{
    public function getHash(): string;

    public function getId(): string;
}
