<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

use EMS\Helpers\ArrayHelper\ArrayHelper;

/**
 * @implements \ArrayAccess<string, ?string>
 */
class ContentTypeFields implements \ArrayAccess
{
    /** @var array<string, ?string> */
    private array $fields = [];

    public const LABEL = 'label';

    private const FIELDS = [
        self::LABEL,
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
        $cleaned = ArrayHelper::map($this->fields, fn ($v) => (\strlen($v) > 0 ? $v : null));

        return $cleaned;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->fields[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->fields[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->fields[$offset]);
    }
}
