<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Field\FilterOptionsType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['required' => true])
            ->add('label', null, ['required' => true])
            ->add('options', FilterOptionsType::class, [
                'attr' => [
                    'class' => 'fields-to-display-by-value',
                ],
            ])
            ->add('save', SubmitEmsType::class, [
                'label' => 'Save',
                'attr' => [
                    'class' => 'btn btn-primary pull-right',
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Filter::class,
        ]);
    }
}
