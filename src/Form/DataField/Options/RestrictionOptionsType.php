<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\Helpers\Translations\Translations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class RestrictionOptionsType extends AbstractType
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
    ) {
    }

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
            ->add('mandatory', CheckboxType::class, [
                'label' => t('field.mandatory', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('mandatory_if', TextType::class, [
                'label' => t('field.mandatory_if', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('minimum_role', RolePickerType::class, [
                'label' => t('field.minimum_role', [], 'emsco-core'),
                'required' => false,
            ])
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
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User must be logged in');
        }

        if (($fieldType->isJsonMenuNestedEditor() || $fieldType->isJsonMenuNestedEditorNode()) && ($jsonMenuNestedEditor = $fieldType->getJsonMenuNestedEditor()) instanceof FieldType) {
            $choices = [];
            foreach ($jsonMenuNestedEditor->getChildren() as $child) {
                if ($child->getDeleted()) {
                    continue;
                }

                $label = Translations::fromArray($child->getDisplayOption('labelTranslations', []))->getTranslation($user->getLocales(), $child->getDisplayOption('label', $child->getName()))->getLabel();
                $choices[$label] = $child->getName();
            }
            $builder->add('json_nested_deny', ChoiceType::class, [
                'label' => t('field.json_nested_deny', [], 'emsco-core'),
                'multiple' => true,
                'required' => false,
                'choices' => $choices,
                'block_prefix' => 'select2',
                'choice_translation_domain' => false,
            ]);
        }

        if ($fieldType->isJsonMenuNestedEditor()) {
            $builder->add('json_nested_max_depth', IntegerType::class, [
                'label' => t('field.json_nested_max_depth', [], 'emsco-core'),
                'required' => false,
            ]);
        }

        if ($fieldType->isJsonMenuNestedEditorNode()) {
            $builder->add('json_nested_is_leaf', CheckboxType::class, [
                'label' => t('field.json_nested_is_leaf', [], 'emsco-core'),
                'required' => false,
            ]);
        }
    }
}
