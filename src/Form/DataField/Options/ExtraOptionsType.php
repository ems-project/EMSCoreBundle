<?php

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * It's a coumpound field for field specific extra option.
 */
class ExtraOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('extra', TextareaType::class, ['attr' => ['rows' => 8], 'required' => false])
            ->add('clear_on_copy', CheckboxType::class, ['required' => false])
            ->add('postProcessing', CodeEditorType::class, ['required' => false])
        ;
    }
}
