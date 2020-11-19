<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\ColorPickerFullType;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class ColorPickerFieldType extends DataFieldType
{
    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'Color picker field';
    }

    /**
     * Get a icon to visually identify a FieldType.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'fa fa-paint-brush';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions($name)
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    public function getParent()
    {
        return ColorPickerFullType::class;
    }

    public function modelTransform($data, FieldType $fieldType)
    {
        $dataField = parent::modelTransform($data, $fieldType);
        if (null !== $data && !\is_string($data)) {
            $dataField->addMessage('Not able to import data from the database:'.\json_encode($data));
            $dataField->setRawData(null);
        }

        return $dataField;
    }
}
