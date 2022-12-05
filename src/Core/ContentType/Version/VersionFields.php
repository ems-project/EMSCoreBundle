<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Version;

use EMS\Helpers\ArrayHelper\ArrayHelper;

/**
 * @implements \ArrayAccess<string, ?string>
 */
class VersionFields implements \ArrayAccess
{
    /** @var array<string, ?string> */
    private array $fields = [];

    final public const DATE_FROM = 'date_from';
    final public const DATE_TO = 'date_to';
    final public const VERSION_TAG = 'version_tag';

    private const FIELDS = [
        self::DATE_FROM,
        self::DATE_TO,
        self::VERSION_TAG,
    ];

    /**
     * @param array<string, ?string> $data
     */
    public function __construct(array $data)
    {
        foreach (self::FIELDS as $field) {
            $this->fields[$field] = $data[$field] ?? null;
        }
    }

    /**
     * @return array<string, ?string>
     */
    public function getFields(): array
    {
        /** @var array<string, ?string> $cleaned */
        $cleaned = ArrayHelper::map($this->fields, fn (?string $v) => (null !== $v && \strlen($v) > 0 ? $v : null));

        return $cleaned;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet($offset): ?string
    {
        return $this->fields[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->fields[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->fields[$offset]);
    }
}
