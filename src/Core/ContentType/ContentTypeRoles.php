<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

use EMS\CoreBundle\Roles;

/**
 * @implements \ArrayAccess<string, string>
 */
class ContentTypeRoles implements \ArrayAccess
{
    /** @var array<string, string> */
    private array $roles = [];

    final public const string VIEW = 'view';
    final public const string CREATE = 'create';
    final public const string EDIT = 'edit';
    final public const string PUBLISH = 'publish';
    final public const string DELETE = 'delete';
    final public const string TRASH = 'trash';
    final public const string ARCHIVE = 'archive';
    final public const string SHOW_LINK_CREATE = 'show_link_create';
    final public const string SHOW_LINK_SEARCH = 'show_link_search';

    private const array TYPES = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::PUBLISH,
        self::DELETE,
        self::TRASH,
        self::ARCHIVE,
        self::SHOW_LINK_CREATE,
        self::SHOW_LINK_SEARCH,
    ];

    /**
     * @param array<string, string> $data
     */
    public function __construct(array $data)
    {
        foreach (self::TYPES as $type) {
            $this->roles[$type] = $data[$type] ?? $this->getDefaultValue($type);
        }
    }

    private function getDefaultValue(string $type): string
    {
        return match ($type) {
            self::VIEW, self::CREATE, self::EDIT => Roles::ROLE_AUTHOR,
            self::PUBLISH => Roles::ROLE_PUBLISHER,
            self::SHOW_LINK_SEARCH, self::SHOW_LINK_CREATE => Roles::ROLE_USER,
            default => 'not-defined',
        };
    }

    /**
     * @return array<string, string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function offsetExists($offset): bool
    {
        return isset($this->roles[$offset]);
    }

    #[\Override]
    public function offsetGet($offset): ?string
    {
        return $this->roles[$offset] ?? null;
    }

    #[\Override]
    public function offsetSet($offset, $value): void
    {
        $this->roles[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset($offset): void
    {
        unset($this->roles[$offset]);
    }
}
