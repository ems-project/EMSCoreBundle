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

    final public const string DISPLAY = 'display';
    final public const string LABEL = 'label';
    final public const string COLOR = 'color';
    final public const string SORT = 'sort';
    final public const string TOOLTIP = 'tooltip';
    final public const string CIRCLES = 'circles';
    final public const string BUSINESS_ID = 'business_id';
    final public const string CATEGORY = 'category';
    final public const string ASSET = 'asset';

    private const array FIELDS = [
        self::DISPLAY,
        self::LABEL,
        self::COLOR,
        self::SORT,
        self::TOOLTIP,
        self::CIRCLES,
        self::BUSINESS_ID,
        self::CATEGORY,
        self::ASSET,
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

    #[\Override]
    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    #[\Override]
    public function offsetGet($offset): ?string
    {
        return $this->fields[$offset] ?? null;
    }

    #[\Override]
    public function offsetSet($offset, $value): void
    {
        $this->fields[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset($offset): void
    {
        unset($this->fields[$offset]);
    }
}
