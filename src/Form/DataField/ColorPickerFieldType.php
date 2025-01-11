<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\ColorPickerFullType;
use EMS\Helpers\Standard\Json;

/**
 * Defined a Container content type.
 * It's used to logically groups subfields together. However a Container is invisible in Elastic search.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class ColorPickerFieldType extends DataFieldType
{
    #[\Override]
    public function getLabel(): string
    {
        return 'Color picker field';
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-paint-brush';
    }

    #[\Override]
    public function getDefaultOptions(string $name): array
    {
        $out = parent::getDefaultOptions($name);

        $out['mappingOptions']['index'] = 'not_analyzed';

        return $out;
    }

    #[\Override]
    public function getParent(): string
    {
        return ColorPickerFullType::class;
    }

    #[\Override]
    public function modelTransform($data, FieldType $fieldType): DataField
    {
        $dataField = parent::modelTransform($data, $fieldType);
        if (null !== $data && !\is_string($data)) {
            $dataField->addMessage('Not able to import data from the database:'.Json::encode($data));
            $dataField->setRawData(null);
        }

        return $dataField;
    }
}
