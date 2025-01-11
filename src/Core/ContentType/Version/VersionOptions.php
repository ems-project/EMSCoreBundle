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

    final public const string DATES_READ_ONLY = 'dates_read_only';
    final public const string DATES_INTERVAL_ONE_DAY = 'dates_interval_one_day';
    final public const string NOT_BLANK_NEW_VERSION = 'not_blank_new_version';

    /**
     * @param array<string, bool> $data
     */
    public function __construct(array $data)
    {
        $this->options[self::DATES_READ_ONLY] = $data[self::DATES_READ_ONLY] ?? true;
        $this->options[self::DATES_INTERVAL_ONE_DAY] = $data[self::DATES_INTERVAL_ONE_DAY] ?? false;
        $this->options[self::NOT_BLANK_NEW_VERSION] = $data[self::NOT_BLANK_NEW_VERSION] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    #[\Override]
    public function offsetGet($offset): ?bool
    {
        return $this->options[$offset] ?? null;
    }

    #[\Override]
    public function offsetSet($offset, $value): void
    {
        $this->options[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }
}
