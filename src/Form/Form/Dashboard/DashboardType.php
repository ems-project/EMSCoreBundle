<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\DashboardPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DashboardType extends AbstractType
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dashboard = $options['data'] ?? null;
        if (!$dashboard instanceof Dashboard) {
            throw new \RuntimeException('Unexpected data type');
        }

        $builder
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
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('role', RolePickerType::class, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('sidebarMenu', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('notificationMenu', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('color', ColorPickerType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('type', DashboardPickerType::class, [
                'required' => true,
                'disabled' => !($options['create'] ?? false),
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ]);

        if ($options['create'] ?? false) {
            $builder
                ->add('create', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn btn-primary btn-sm ',
                    ],
                    'icon' => 'fa fa-save',
                ]);
        } else {
            $builder->add('options', DashboardOptionsType::class, [
                'dashboard' => $this->dashboardService->get($dashboard->getType()),
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dashboard::class,
            'label_format' => 'dashboard.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'create' => false,
        ]);
    }
}
