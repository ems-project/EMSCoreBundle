<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

use EMS\Helpers\Standard\Type;

/**
 * @implements \ArrayAccess<string, bool>
 */
class ContentTypeSettings implements \ArrayAccess
{
    /** @var array<string, bool|string[]> */
    private array $settings = [];

    final public const string TASKS_ENABLED = 'tasks_enabled';
    final public const string TASKS_TITLES = 'tasks_titles';
    final public const string HIDE_REVISION_SIDEBAR = 'hide_revision_sidebar';

    private const array SETTINGS = [
        self::TASKS_ENABLED,
        self::TASKS_TITLES,
        self::HIDE_REVISION_SIDEBAR,
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        foreach (self::SETTINGS as $field) {
            $this->settings[$field] = match ($field) {
                self::TASKS_TITLES => $data[$field] ?? [],
                default => $data[$field] ?? false,
            };
        }
    }

    /**
     * @return array<string, bool|string[]>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getSettingBool(string $settingName): bool
    {
        return isset($this->settings[$settingName]) && Type::bool($this->settings[$settingName]);
    }

    /**
     * @return string[]
     */
    public function getSettingArrayString(string $settingName): array
    {
        return isset($this->settings[$settingName]) ? Type::array($this->settings[$settingName]) : [];
    }

    #[\Override]
    public function offsetExists($offset): bool
    {
        return isset($this->settings[$offset]);
    }

    /**
     * @return bool|string[]
     */
    #[\Override]
    public function offsetGet($offset): bool|array
    {
        return $this->settings[$offset];
    }

    #[\Override]
    public function offsetSet($offset, $value): void
    {
        $this->settings[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset($offset): void
    {
        unset($this->settings[$offset]);
    }
}
