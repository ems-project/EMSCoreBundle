<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

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

    private const array OPTIONS = [
        self::BODY,
        self::HEADER,
        self::FOOTER,
        self::FILENAME,
        self::MIMETYPE,
        self::FILE_DISPOSITION,
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

    #[\Override]
    public function offsetGet($offset): ?string
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
