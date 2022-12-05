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

    final public const SIMPLIFIED_UI = 'simplified_ui';

    /**
     * @param array<string, bool> $data
     */
    public function __construct(array $data)
    {
        $this->options[self::SIMPLIFIED_UI] = $data[self::SIMPLIFIED_UI] ?? false;
    }

    public function isEnabled(string $option): bool
    {
        return true === $this->options[$option];
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

    public function offsetGet($offset): ?bool
    {
        return $this->options[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->options[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }
}
