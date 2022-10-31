<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Version;

/**
 * @implements \ArrayAccess<string, bool>
 */
class VersionOptions implements \ArrayAccess
{
    /** @var array<string, bool> */
    private array $options = [];

    public const DATES_READ_ONLY = 'dates_read_only';
    public const DATES_INTERVAL_ONE_DAY = 'dates_interval_one_day';

    /**
     * @param array<string, bool> $data
     */
    public function __construct(array $data)
    {
        $this->options[self::DATES_READ_ONLY] = $data[self::DATES_READ_ONLY] ?? true;
        $this->options[self::DATES_INTERVAL_ONE_DAY] = $data[self::DATES_INTERVAL_ONE_DAY] ?? false;
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
