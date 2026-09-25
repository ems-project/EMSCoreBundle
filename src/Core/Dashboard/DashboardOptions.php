<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

use EMS\Helpers\Standard\Type;

/**
 * @implements \ArrayAccess<string, string>
 */
class DashboardOptions implements \ArrayAccess
{
    /** @var array<string, string> */
    private array $options = [];

    final public const string BODY = 'body';
    final public const string HEADER = 'header';
    final public const string FOOTER = 'footer';

    final public const string FILENAME = 'filename';
    final public const string MIMETYPE = 'mimetype';
    final public const string FILE_DISPOSITION = 'fileDisposition';
    final public const string ENVIRONMENTS = 'environments';
    final public const string CONTENT_TYPES = 'contentTypes';
    final public const string SORT_BY = 'sortBy';
    final public const string SORT_ORDER = 'sortOrder';
    final public const string MINIMUM_SHOULD_MATCH = 'minimumShouldMatch';
    final public const string FILTERS = 'filters';
    final public const string SORT_OPTIONS = 'sortOptions';
    final public const string AGGREGATE_OPTIONS = 'aggregateOptions';
    final public const string SEARCH_FIELD_OPTIONS = 'searchFieldOptions';

    private const array OPTIONS = [
        self::BODY,
        self::HEADER,
        self::FOOTER,
        self::FILENAME,
        self::MIMETYPE,
        self::FILE_DISPOSITION,
        self::ENVIRONMENTS,
        self::CONTENT_TYPES,
        self::SORT_BY,
        self::SORT_ORDER,
        self::MINIMUM_SHOULD_MATCH,
        self::FILTERS,
        self::SORT_OPTIONS,
        self::AGGREGATE_OPTIONS,
        self::SEARCH_FIELD_OPTIONS,
    ];

    /**
     * @param array<string, ?string> $data
     */
    public function __construct(array $data)
    {
        foreach (self::OPTIONS as $field) {
            if (isset($data[$field])) {
                $this->options[$field] = $data[$field];
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return \array_filter($this->options);
    }

    #[\Override]
    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    /**
     * @return mixed[]|string|null
     */
    #[\Override]
    public function offsetGet($offset): mixed
    {
        return $this->options[$offset] ?? null;
    }

    public function getNullableString(string $offset, ?string $default = null): ?string
    {
        return isset($this->options[$offset]) ? Type::string($this->options[$offset]) : $default;
    }

    /**
     * @param  mixed[] $default
     * @return mixed[]
     */
    public function getArray(string $offset, array $default = []): array
    {
        return Type::array($this->options[$offset] ?? $default);
    }

    #[\Override]
    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            return;
        }

        $this->options[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset($offset): void
    {
        unset($this->options[$offset]);
    }

    public function getInteger(string $offset, ?int $default = null): int
    {
        return Type::integer($this->options[$offset] ?? $default);
    }
}
