<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

class JsonMenuNestedConfigException extends \RuntimeException
{
    public static function nodeNotFound(): self
    {
        return new self('json_menu_nested.error.node_not_found');
    }
}
