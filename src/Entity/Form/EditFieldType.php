<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\FieldType;

/**
 * EditFieldType.
 */
class EditFieldType
{
    public function __construct(private FieldType $fieldType)
    {
    }

    /**
     * @return FieldType
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * @param FieldType $fieldType
     *
     * @return EditFieldType
     */
    public function setFieldType($fieldType)
    {
        $this->fieldType = $fieldType;

        return $this;
    }
}
