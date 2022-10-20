<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

/**
 * @implements \ArrayAccess<string, ?string>
 */
class ContentTypeRoles implements \ArrayAccess
{
    /** @var array<string, ?string> */
    private array $roles = [];

    public const DELETE = 'delete';

    private const TYPES = [self::DELETE];

    /**
     * @param array<string, ?string> $data
     */
    public function __construct(array $data)
    {
        foreach (self::TYPES as $type) {
            $this->roles[$type] = $data[$type] ?? null;
        }
    }

    /**
     * @return array<string, ?string>
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
