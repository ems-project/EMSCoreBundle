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

    public const ARCHIVE = 'archive';
    public const VIEW = 'view';
    public const DELETE = 'delete';
    public const SHOW_LINK_CREATE = 'show_link_create';
    public const SHOW_LINK_SEARCH = 'show_link_search';

    private const TYPES = [
        self::ARCHIVE,
        self::VIEW,
        self::DELETE,
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
        switch ($type) {
            case self::VIEW:
                return Roles::ROLE_AUTHOR;
            case self::SHOW_LINK_SEARCH:
            case self::SHOW_LINK_CREATE:
                return Roles::ROLE_USER;
            default:
                return 'not-defined';
        }
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

    public function offsetGet($offset)
    {
        return $this->roles[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->roles[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->roles[$offset]);
    }
}
