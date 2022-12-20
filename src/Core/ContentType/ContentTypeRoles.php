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

    final public const VIEW = 'view';
    final public const CREATE = 'create';
    final public const EDIT = 'edit';
    final public const PUBLISH = 'publish';
    final public const DELETE = 'delete';
    final public const TRASH = 'trash';
    final public const ARCHIVE = 'archive';
    final public const SHOW_LINK_CREATE = 'show_link_create';
    final public const SHOW_LINK_SEARCH = 'show_link_search';

    private const TYPES = [
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

    public function offsetExists($offset): bool
    {
        return isset($this->roles[$offset]);
    }

    public function offsetGet($offset): ?string
    {
        return $this->roles[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->roles[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->roles[$offset]);
    }
}
