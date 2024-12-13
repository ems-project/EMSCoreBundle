<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

use EMS\CoreBundle\Entity\FieldType;

class JsonMenuNestedNode
{
    /**
     * @param string[] $deny
     */
    private function __construct(
        private readonly FieldType $fieldType,
        public readonly int $id,
        public readonly string $type,
        public readonly string $label,
        public readonly string $role,
        public readonly ?string $icon,
        public readonly array $deny,
        public readonly bool $leaf,
    ) {
    }

    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }

    public static function fromFieldType(FieldType $fieldType): self
    {
        return new self(
            $fieldType,
            $fieldType->getId(),
            $fieldType->getName(),
            $fieldType->getDisplayOption('label', $fieldType->getName()),
            $fieldType->getMinimumRole(),
            $fieldType->getDisplayOption('icon', null),
            $fieldType->getRestrictionOption('json_nested_deny', []),
            $fieldType->getRestrictionOption('json_nested_is_leaf', false)
        );
    }
}
