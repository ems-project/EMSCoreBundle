<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DashboardType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        \dump($options);
        $builder
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('name', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('icon', IconPickerType::class, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dashboard::class,
            'label_format' => 'dashboard.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}
