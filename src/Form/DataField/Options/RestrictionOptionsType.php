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
        $parent = $fieldType->getParent();

        $builder
            ->add('mandatory', CheckboxType::class, ['required' => false])
            ->add('mandatory_if', TextType::class, ['required' => false])
            ->add('minimum_role', RolePickerType::class, ['required' => false])
        ;

        if (JsonMenuNestedEditorFieldType::class === $fieldType->getType()) {
            $this->addJsonMenuNestedRestrictionFields($builder, $fieldType, true);
        }
        if ($parent && JsonMenuNestedEditorFieldType::class === $parent->getType()) {
            $this->addJsonMenuNestedRestrictionFields($builder, $parent, false);
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
    private function addJsonMenuNestedRestrictionFields(FormBuilderInterface $builder, FieldType $jsonMenuNested, bool $isRoot): void
    {
        $choices = [];
        foreach ($jsonMenuNested->getJsonMenuNestedNodeChildren() as $child) {
            $choices[$child->getName()] = $child->getName();
        }
        $builder->add('json_nested_deny', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $choices,
            'block_prefix' => 'select2',
        ]);

        if ($isRoot) {
            $builder->add('json_nested_max_depth', IntegerType::class);
        } else {
            $builder->add('json_nested_is_leaf', CheckboxType::class, ['required' => false]);
        }
    }
}
