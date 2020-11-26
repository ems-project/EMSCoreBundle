<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class RevisionJsonMenuNestedType extends AbstractType
{
    /** @var FormRegistryInterface */
    private $formRegistry;

    public function __construct(FormRegistryInterface $formRegistry)
    {
        $this->formRegistry = $formRegistry;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<mixed>                               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];

        $builder->add('label', TextType::class, [
            'constraints' => [new NotBlank()],
        ]);

        foreach ($fieldType->getChildren() as $child) {
            /** @var FieldType $child */
            if ($child->getDeleted()) {
                continue;
            }

            $options = \array_merge([
                'metadata' => $child,
                'label' => false,
            ], $child->getDisplayOptions());

            $required = $child->getRestrictionOptions()['mandatory'] ?? false;

            if ($required) {
                $options['constraints'] = [new NotBlank()];
            }

            $builder->add($child->getName(), $child->getType(), $options);
            $builder->get($child->getName())
                ->addViewTransformer(new DataFieldViewTransformer($child, $this->formRegistry))
                ->addModelTransformer(new DataFieldModelTransformer($child, $this->formRegistry));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['field_type'])
            ->setAllowedTypes('field_type', FieldType::class)
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'container_field_type';
    }
}
