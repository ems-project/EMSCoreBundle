<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

/**
 * @implements \ArrayAccess<string, bool>
 */
class ContentTypeSettings implements \ArrayAccess
{
    /** @var array<string, bool> */
    private array $settings = [];

    final public const TASKS_ENABLED = 'tasks_enabled';

    private const SETTINGS = [
        self::TASKS_ENABLED,
    ];

    /**
     * @param array<string, bool> $data
     */
    public function __construct(array $data)
    {
        foreach (self::SETTINGS as $field) {
            $this->settings[$field] = $data[$field] ?? false;
        }
    }

    /**
     * @return array<string, bool>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->settings[$offset]);
    }

    public function offsetGet($offset): bool
    {
        return $this->settings[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->settings[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->settings[$offset]);
    }
}
