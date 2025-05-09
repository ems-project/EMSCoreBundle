<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\ActionFieldType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class ExtraOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed>      $builder
     * @param array{ 'field_type': FieldType } $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldType = $options['field_type'];

        if (ActionFieldType::class === $fieldType->getType()) {
            $builder->add('config', CodeEditorType::class, [
                'label' => 'Action config',
                'required' => true,
                'language' => 'ace/mode/json',
            ]);
        } else {
            $this->addDefaultExtraOptions($builder);
        }
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
    private function addDefaultExtraOptions(FormBuilderInterface $builder): void
    {
        $builder
            ->add('extra', TextareaType::class, ['attr' => ['rows' => 8], 'required' => false])
            ->add('clear_on_copy', CheckboxType::class, ['required' => false])
            ->add('postProcessing', CodeEditorType::class, ['required' => false])
        ;
    }
}
