<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
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

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class DashboardType extends AbstractType
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Dashboard $dashboard */
        $dashboard = $options['data'];

        $builder
            ->add('name', null, [
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('icon', IconPickerType::class, [
                'label' => t('field.icon', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('label', null, [
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('role', RolePickerType::class, [
                'label' => t('field.role', [], 'emsco-core'),
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('sidebarMenu', CheckboxType::class, [
                'label' => t('field.is_menu_sidebar', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('notificationMenu', CheckboxType::class, [
                'label' => t('field.is_menu_notification', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('color', ColorPickerType::class, [
                'label' => t('field.color', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('type', DashboardPickerType::class, [
                'label' => t('field.type', [], 'emsco-core'),
                'required' => true,
                'disabled' => !($options['create'] ?? false),
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ]);

        if (false === $options['create']) {
            $builder->add('options', DashboardOptionsType::class, [
                'dashboard' => $this->dashboardService->get($dashboard->getType()),
            ]);
        }

        $builder->add('save', SubmitEmsType::class, [
            'label' => t('action.save', [], 'emsco-core'),
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dashboard::class,
            'create' => false,
        ]);
    }
}
