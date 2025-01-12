<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField\Options;

use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class ExtraOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
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
