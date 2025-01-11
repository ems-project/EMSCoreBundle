<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

/**
 * @implements \ArrayAccess<string, bool|mixed|null>
 */
class UserOptions implements \ArrayAccess
{
    /** @var array{simplified_ui?: bool, allowed_configure_wysiwyg?: bool, custom_options?: mixed} */
    private array $options = [];

    final public const string SIMPLIFIED_UI = 'simplified_ui';
    final public const string ALLOWED_CONFIGURE_WYSIWYG = 'allowed_configure_wysiwyg';
    final public const string CUSTOM_OPTIONS = 'custom_options';
    final public const array ALL_MEMBERS = [self::SIMPLIFIED_UI, self::ALLOWED_CONFIGURE_WYSIWYG, self::CUSTOM_OPTIONS];

    /**
     * @param array{simplified_ui?: bool, allowed_configure_wysiwyg?: bool, custom_options?: mixed} $data
     */
    public function __construct(array $data)
    {
        $this->options[self::SIMPLIFIED_UI] = ($data[self::SIMPLIFIED_UI] ?? false);
        $this->options[self::ALLOWED_CONFIGURE_WYSIWYG] = ($data[self::ALLOWED_CONFIGURE_WYSIWYG] ?? false);
        $this->options[self::CUSTOM_OPTIONS] = ($data[self::CUSTOM_OPTIONS] ?? []);
    }

    public function isEnabled(string $option): bool
    {
        if (!\in_array($option, [self::SIMPLIFIED_UI, self::ALLOWED_CONFIGURE_WYSIWYG])) {
            throw new \RuntimeException(\sprintf('The field %s is not a boolean field', $option));
        }

        return true === ($this->options[$option] ?? null);
    }

    /**
     * @return array{simplified_ui?: bool, custom_options?: mixed}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    #[\Override]
    public function offsetExists($offset): bool
    {
        if (!\in_array($offset, self::ALL_MEMBERS)) {
            throw new \RuntimeException(\sprintf('The field %s is not supported', $offset));
        }

        return isset($this->options[$offset]);
    }

    #[\Override]
    public function offsetGet($offset): mixed
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
