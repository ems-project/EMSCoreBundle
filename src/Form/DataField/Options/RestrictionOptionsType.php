<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class RestrictionOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];

        $builder
            ->add('mandatory', CheckboxType::class, ['required' => false])
            ->add('mandatory_if', TextType::class, ['required' => false])
            ->add('minimum_role', RolePickerType::class, ['required' => false])
        ;

        $this->addJsonMenuNestedRestrictionFields($builder, $fieldType);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function addJsonMenuNestedRestrictionFields(FormBuilderInterface $builder, FieldType $fieldType): void
    {
        if ($fieldType->isJsonMenuNestedEditor() || $fieldType->isJsonMenuNestedEditorNode()) {
            if ($jsonMenuNestedEditor = $fieldType->getJsonMenuNestedEditor()) {
                $choices = [];

                foreach ($jsonMenuNestedEditor->getChildren() as $child) {
                    if ($child->getDeleted()) {
                        continue;
                    }
                    $choices[$child->getName()] = $child->getName();
                }

                $builder->add('json_nested_deny', ChoiceType::class, [
                    'multiple' => true,
                    'required' => false,
                    'choices' => $choices,
                    'block_prefix' => 'select2',
                ]);
            }
        }

        if ($fieldType->isJsonMenuNestedEditor()) {
            $builder->add('json_nested_max_depth', IntegerType::class, ['required' => false]);
        }

        if ($fieldType->isJsonMenuNestedEditorNode()) {
            $builder->add('json_nested_is_leaf', CheckboxType::class, ['required' => false]);
        }
    }
}
