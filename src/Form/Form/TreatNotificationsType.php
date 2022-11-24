<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TreatNotificationsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $notifications = $options['notifications'];

        $builder->add('notifications', CollectionType::class, [
                'entry_type' => CheckboxType::class,
                'allow_add' => true,
                'required' => false,
                'entry_options' => [
                    'label' => null,
                    'required' => false,
                ],
            ])
            ->add('publishTo', EnvironmentPickerType::class, [
                    'multiple' => false,
                    'required' => false,
            ])
//             ->add('unpublishFrom', EnvironmentPickerType::class, [
//                     'multiple' => false,
//                     'required' => false,
//             ] )
            ->add('response', TextareaType::class, [
                    'attr' => [
                            'class' => 'ckeditor',
                    ],
            ])
            ->add('accept', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn btn-success btn-md',
                    ],
                    'disabled' => true,
                    'icon' => 'fa fa-check',
            ])
            ->add('reject', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn btn-danger btn-md',
                    ],
                    'icon' => 'fa fa-ban',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'notifications' => [],
        ]);
    }
}
