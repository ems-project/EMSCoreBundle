<?php

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\FieldType;

/**
 * EditFieldType.
 */
class EditFieldType
{
    private FieldType $fieldType;

    public function __construct(FieldType $fieldType)
    {
        $this->fieldType = $fieldType;
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
