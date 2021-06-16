<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\JsonMenuNestedEditorFieldType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestrictionOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];

        $builder
            ->add('mandatory', CheckboxType::class, ['required' => false])
            ->add('mandatory_if', TextType::class, ['required' => false])
            ->add('minimum_role', RolePickerType::class, ['required' => false])
        ;

        if ($fieldType->isJsonMenuNestedEditorField()) {
            $this->addJsonMenuNestedRestrictionFields($builder, $fieldType);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     */
    private function addJsonMenuNestedRestrictionFields(FormBuilderInterface $builder, FieldType $fieldType): void
    {
        $nodes = $fieldType->getJsonMenuNestedEditorNodes();

        $builder->add('json_nested_deny', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => array_map(fn (array $node) => $node['name'], $nodes),
            'block_prefix' => 'select2',
        ]);

        if ($fieldType->getType() === JsonMenuNestedEditorFieldType::class) {
            $builder->add('json_nested_max_depth', IntegerType::class);
        } else {
            $builder->add('json_nested_is_leaf', CheckboxType::class, ['required' => false]);
        }
    }
}
