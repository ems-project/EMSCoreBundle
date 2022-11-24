<?php

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\FieldType;

/**
 * EditFieldType.
 */
class EditFieldType
{
    /** @var FieldType */
    private $fieldType;

    public function __construct(FieldType $fieldType)
    {
        $this->fieldType = $fieldType;
    }

    /**
     * @return \EMS\CoreBundle\Entity\FieldType
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * @param \EMS\CoreBundle\Entity\FieldType $fieldType
     *
     * @return EditFieldType
     */
    public function setFieldType($fieldType)
    {
        $this->fieldType = $fieldType;

        return $this;
    }
}
