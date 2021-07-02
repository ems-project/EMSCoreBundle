<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class MultiplexedTabContainerFieldType extends DataFieldType
{
    public function getLabel()
    {
        return 'Multiplexed Tab Container';
    }

    public static function isContainer()
    {
        return true;
    }

    public static function isNested()
    {
        return true;
    }

    public function buildOptionsForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        $optionsForm->get('displayOptions')->add('values', TextareaType::class, [
            'required' => false,
        ])->add('labels', TextareaType::class, [
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            $optionsForm->remove('mappingOptions');
        }

        if ($optionsForm->has('restrictionOptions')) {
            $optionsForm->remove('restrictionOptions');
        }

        if ($optionsForm->has('migrationOptions')) {
            $optionsForm->remove('migrationOptions');
        }
    }

    public function generateMapping(FieldType $current, $withPipeline)
    {
        $values = $current->getDisplayOption('values');
        if (null === $values) {
            return [$current->getName() => []];
        }

        $values = self::textAreaToArray($values);
        $mapping = [];
        foreach ($values as $value) {
            $mapping[$value] = [];
        }

        return $mapping;
    }

    /**
     * @return string[]
     */
    public static function getJsonNames(FieldType $current): array
    {
        $values = $current->getDisplayOption('values');
        if (null === $values) {
            return [$current->getName()];
        }

        return self::textAreaToArray($values);
    }
}
