<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

class JsonMenuNestedConfigException extends \RuntimeException
{
    public static function nodeNotFound(): self
    {
        return new self('Item type not found');
    }
}
