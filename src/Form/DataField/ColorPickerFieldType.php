<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
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
    public function getLabel(): string
    {
        return 'Color picker field';
    }

    public static function getIcon(): string
    {
        return 'fa fa-paint-brush';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    public function getParent(): string
    {
        return ColorPickerFullType::class;
    }

    /**
     * {@inheritDoc}
     */
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::modelTransform($data, $fieldType);
        if (null !== $data && !\is_string($data)) {
            $dataField->addMessage('Not able to import data from the database:'.\json_encode($data, JSON_THROW_ON_ERROR));
            $dataField->setRawData(null);
        }

        return $dataField;
    }
}
