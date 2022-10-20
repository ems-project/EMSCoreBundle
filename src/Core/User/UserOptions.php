<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

/**
 * @implements \ArrayAccess<string, bool>
 */
class UserOptions implements \ArrayAccess
{
    /** @var array<string, bool> */
    private array $options = [];

    public const SIMPLIFIED_UI = 'simplified_ui';

    /**
     * @param array<string, bool> $data
     */
    public function __construct(array $data)
    {
        $this->options[self::SIMPLIFIED_UI] = $data[self::SIMPLIFIED_UI] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->options[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->options[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->options[$offset]);
    }
}
